<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use League\Csv\Reader;

class AiChatController extends Controller
{
    private string $mistralApiKey;
    private string $model   = 'mistral-large-latest';
    private string $apiBase = 'https://api.mistral.ai/v1/chat/completions';

    public function __construct()
    {
        $key = config('services.mistral.api_key') ?? env('MISTRAL_API_KEY');

        if (!$key) {
            throw new \RuntimeException('MISTRAL_API_KEY is not set. Add it to your .env file.');
        }

        $this->mistralApiKey = $key;
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
                'error'   => 'No CSV file found. Please upload at least one file first.',
            ], 422);
        }

        // 2. Retrieve conversation history from session
        $history = Session::get('chat_history', []);

        // 3. Call Mistral API
        $response = $this->callMistral($csvContext, $history, $userMessage);

        if (!$response['success']) {
            return response()->json([
                'success' => false,
                'error'   => $response['error'],
            ], 500);
        }

        $aiReply = $response['content'];

        // 4. Persist conversation history (keep last 20 turns)
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

        $fullPath = storage_path("app/private/{$path}");
        if (!file_exists($fullPath)) {
            $fullPath = storage_path("app/{$path}");
        }

        $csvFiles = Session::get('csv_files', []);
        $csvFiles = array_values(array_filter($csvFiles, fn($f) => $f['name'] !== $originalName));

        $csvFiles[] = [
            'name'     => $originalName,
            'path'     => $path,
            'uploaded' => now()->format('H:i'),
        ];

        if (count($csvFiles) > 5) {
            $csvFiles = array_slice($csvFiles, -5);
        }

        Session::put('csv_files', $csvFiles);

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
        return "The user has uploaded {$count} CSV file(s). Analyze them together when relevant.\n\n"
            . implode("\n\n", $allContexts);
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
     * Call the Mistral API.
     * Mistral uses OpenAI-compatible format: system + user/assistant messages.
     */
    private function callMistral(string $csvContext, array $history, string $userMessage): array
    {
        try {
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
- Respond in the same language the user uses (English, French, or Dutch).
- Never make up data that is not in the context.
PROMPT;

            // Build messages — Mistral uses same format as OpenAI
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];

            foreach ($history as $turn) {
                $messages[] = [
                    'role'    => $turn['role'], // 'user' or 'assistant'
                    'content' => $turn['content'],
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $userMessage];

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->mistralApiKey}",
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post($this->apiBase, [
                'model'       => $this->model,
                'messages'    => $messages,
                'temperature' => 0.3,
                'max_tokens'  => 1024,
            ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'error'   => 'Mistral API error: ' . $response->status() . ' — ' . $response->body(),
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