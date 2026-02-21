<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use League\Csv\Reader;

class AiChatController extends Controller
{
    private string $groqApiKey;
    private string $groqModel = 'llama-3.3-70b-versatile';

    public function __construct()
    {
        $key = config('services.groq.api_key') ?? env('GROQ_API_KEY');

        if (!$key) {
            throw new \RuntimeException('GROQ_API_KEY is not set. Add it to your .env file.');
        }

        $this->groqApiKey = $key;
    }

    /**
     * Show the chat page
     */
    public function index()
    {
        return view('ai-chat.index');
    }

    /**
     * Handle a chat message — supports multiple files + history
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $userMessage = $request->input('message');

        // 1. Build context from ALL uploaded files
        $csvContext = $this->buildMultiFileCsvContext();

        if (!$csvContext) {
            return response()->json([
                'success' => false,
                'error'   => 'Aucun fichier CSV trouvé. Veuillez d\'abord uploader au moins un fichier.',
            ], 422);
        }

        // 2. Retrieve conversation history from session
        $history = Session::get('chat_history', []);

        // 3. Build messages array for Groq
        $messages = $this->buildMessages($history, $csvContext, $userMessage);

        // 4. Call Groq API
        $response = $this->callGroq($messages);

        if (!$response['success']) {
            return response()->json([
                'success' => false,
                'error'   => $response['error'],
            ], 500);
        }

        $aiReply = $response['content'];

        // 5. Persist conversation history (keep last 20 turns)
        $history[] = ['role' => 'user',      'content' => $userMessage];
        $history[] = ['role' => 'assistant', 'content' => $aiReply];
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        Session::put('chat_history', $history);

        return response()->json([
            'success' => true,
            'reply'   => $aiReply,
        ]);
    }

    /**
     * Upload a CSV file — ADDS to the list, does not replace
     */
    public function uploadCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file         = $request->file('csv_file');
        $originalName = $file->getClientOriginalName();
        $path         = $file->store('csv_uploads', 'local');

        // Try both possible storage paths
        $fullPath = storage_path("app/private/{$path}");
        if (!file_exists($fullPath)) {
            $fullPath = storage_path("app/{$path}");
        }

        // Load existing files list from session
        $csvFiles = Session::get('csv_files', []);

        // If same filename exists, replace it
        $csvFiles = array_values(array_filter($csvFiles, fn($f) => $f['name'] !== $originalName));

        // Add new file
        $csvFiles[] = [
            'name'     => $originalName,
            'path'     => $path,
            'uploaded' => now()->format('H:i'),
        ];

        // Cap at 5 files maximum
        if (count($csvFiles) > 5) {
            $csvFiles = array_slice($csvFiles, -5);
        }

        Session::put('csv_files', $csvFiles);

        // Return preview of the newly uploaded file
        $preview = $this->getPreviewRows($fullPath, 5);

        return response()->json([
            'success'   => true,
            'file'      => [
                'name'     => $originalName,
                'path'     => $path,
                'uploaded' => now()->format('H:i'),
                'rows'     => count($preview['rows'] ?? []),
                'cols'     => count($preview['headers'] ?? []),
            ],
            'preview'   => $preview,
            'fileCount' => count($csvFiles),
        ]);
    }

    /**
     * Remove a specific file from the session
     */
    public function removeFile(Request $request)
    {
        $request->validate(['path' => 'required|string']);

        $csvFiles = Session::get('csv_files', []);
        $csvFiles = array_values(array_filter($csvFiles, fn($f) => $f['path'] !== $request->input('path')));

        Session::put('csv_files', $csvFiles);

        if (empty($csvFiles)) {
            Session::forget('chat_history');
        }

        return response()->json([
            'success'   => true,
            'fileCount' => count($csvFiles),
        ]);
    }

    /**
     * Clear chat history only (keep files)
     */
    public function clearHistory()
    {
        Session::forget('chat_history');
        return response()->json(['success' => true]);
    }

    /**
     * Clear everything — files and history
     */
    public function clearAll()
    {
        Session::forget('chat_history');
        Session::forget('csv_files');
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────

    /**
     * Build combined context from ALL files in session.
     */
    private function buildMultiFileCsvContext(): ?string
    {
        $csvFiles = Session::get('csv_files', []);
        if (empty($csvFiles)) return null;

        // Adjust rows per file based on number of files to stay within token limits
        $fileCount   = count($csvFiles);
        $rowsPerFile = match(true) {
            $fileCount >= 4 => 25,
            $fileCount === 3 => 35,
            $fileCount === 2 => 50,
            default          => 80,
        };

        $allContexts = [];
        $fileIndex   = 1;

        foreach ($csvFiles as $fileInfo) {
            $fullPath = storage_path("app/private/{$fileInfo['path']}");
            if (!file_exists($fullPath)) {
                $fullPath = storage_path("app/{$fileInfo['path']}");
            }
            if (!file_exists($fullPath)) continue;

            $context = $this->parseCsvFile($fullPath, $fileInfo['name'], $fileIndex, $rowsPerFile);
            if ($context) {
                $allContexts[] = $context;
                $fileIndex++;
            }
        }

        if (empty($allContexts)) return null;

        $count = count($allContexts);
        $intro = "The user has uploaded {$count} CSV file(s). Analyze them together when relevant.\n\n";
        return $intro . implode("\n\n", $allContexts);
    }

    /**
     * Parse a single CSV file into a labeled text block.
     */
    private function parseCsvFile(string $fullPath, string $fileName, int $index, int $maxRows): ?string
    {
        try {
            $csv = Reader::createFromFileObject(new \SplFileObject($fullPath, 'r'));
            $csv->setHeaderOffset(0);

            $headers = $csv->getHeader();
            $records = iterator_to_array($csv->getRecords(), false);
            $total   = count($records);
            $sample  = array_slice($records, 0, $maxRows);

            $lines   = [];
            $lines[] = "=== FILE {$index}: {$fileName} ===";
            $lines[] = "Total rows: {$total} | Columns (" . count($headers) . "): " . implode(', ', $headers);
            $lines[] = implode(' | ', $headers);

            foreach ($sample as $row) {
                $lines[] = implode(' | ', array_values($row));
            }

            if ($total > $maxRows) {
                $lines[] = "... and " . ($total - $maxRows) . " more rows not shown.";
            }

            $lines[] = "=== END FILE {$index} ===";

            return implode("\n", $lines);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Build messages array for Groq with multi-file awareness.
     */
    private function buildMessages(array $history, string $csvContext, string $userMessage): array
    {
        $systemPrompt = <<<PROMPT
You are an expert data analyst assistant. The user has uploaded one or more CSV files.

{$csvContext}

Instructions:
- Answer questions accurately based ONLY on the data provided above.
- When multiple files are present, compare or combine them when relevant.
- Always mention which file(s) you are referring to in your answer.
- When asked for top/bottom N items, compute them from the data given.
- If a question cannot be answered from the data, say so clearly.
- Format numbers nicely (e.g. 1,234 instead of 1234).
- Use bullet points or Markdown tables when it improves clarity.
- Keep answers concise but complete.
- Respond in the same language the user uses (French, English, or Dutch).
- Never make up data that is not in the context.
PROMPT;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($history as $turn) {
            $messages[] = $turn;
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    /**
     * Call the Groq Chat Completion API.
     */
    private function callGroq(array $messages): array
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->groqApiKey}",
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => $this->groqModel,
                'messages'    => $messages,
                'temperature' => 0.3,
                'max_tokens'  => 1024,
            ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'error'   => 'Groq API error: ' . $response->status(),
                ];
            }

            /** @var array<string, mixed> $data */
            $data    = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? 'No response from AI.';

            return ['success' => true, 'content' => $content];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Return the first N rows of a CSV for preview.
     */
    private function getPreviewRows(string $fullPath, int $limit = 5): array
    {
        try {
            $csv = Reader::createFromFileObject(new \SplFileObject($fullPath, 'r'));
            $csv->setHeaderOffset(0);
            $headers = $csv->getHeader();
            $rows    = [];
            foreach ($csv->getRecords() as $record) {
                $rows[] = $record;
                if (count($rows) >= $limit) break;
            }
            return ['headers' => $headers, 'rows' => $rows];
        } catch (\Exception $e) {
            return [];
        }
    }
}