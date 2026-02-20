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
        $this->groqApiKey = config('services.groq.api_key');
    }

    /**
     * Show the chat page
     */
    public function index()
    {
        return view('ai-chat.index');
    }

    /**
     * Handle a chat message
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message'  => 'required|string|max:1000',
            'csv_path' => 'nullable|string', // path stored in session after upload
        ]);

        $userMessage = $request->input('message');

        // 1. Build CSV context from the uploaded file
        $csvContext = $this->buildCsvContext($request->input('csv_path'));

        if (!$csvContext) {
            return response()->json([
                'success' => false,
                'error'   => 'Aucun fichier CSV trouvé. Veuillez d\'abord uploader un fichier.',
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

        // 5. Persist conversation history (keep last 10 turns to avoid token overflow)
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
     * Upload a CSV file and store its path in the session
     */
    public function uploadCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10 MB max
        ]);

        $path = $request->file('csv_file')->store('csv_uploads', 'local');

        // Clear previous chat history when a new file is uploaded
        Session::forget('chat_history');
        Session::put('csv_path', $path);

        // Return a quick preview (first 5 rows)
        $preview = $this->getPreviewRows(storage_path("app/{$path}"), 5);

        return response()->json([
            'success'  => true,
            'csv_path' => $path,
            'preview'  => $preview,
        ]);
    }

    /**
     * Clear chat history
     */
    public function clearHistory()
    {
        Session::forget('chat_history');
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────

    /**
     * Parse the CSV and return a compact text representation for the LLM context.
     * We include: column names, row count, and up to 50 sample rows.
     */
    private function buildCsvContext(?string $csvPath): ?string
    {
        // Prefer the session path if none passed explicitly
        $csvPath = $csvPath ?? Session::get('csv_path');
        if (!$csvPath) return null;

        $fullPath = storage_path("app/private/{$csvPath}");
        if (!file_exists($fullPath)) return null;

        try {
            $csv = Reader::createFromFileObject(new \SplFileObject($fullPath, 'r'));


            $csv->setHeaderOffset(0);

            $headers = $csv->getHeader();
            $records = iterator_to_array($csv->getRecords(), false);
            $total   = count($records);

            // Take up to 50 rows as sample data
            $sample = array_slice($records, 0, 50);

            $lines   = [];
            $lines[] = "=== CSV DATA CONTEXT ===";
            $lines[] = "Total rows: {$total}";
            $lines[] = "Columns (" . count($headers) . "): " . implode(', ', $headers);
            $lines[] = "";
            $lines[] = "Sample data (up to 50 rows):";
            $lines[] = implode(' | ', $headers); // header row

            foreach ($sample as $row) {
                $lines[] = implode(' | ', array_values($row));
            }

            if ($total > 50) {
                $lines[] = "... and " . ($total - 50) . " more rows not shown.";
            }

            $lines[] = "=== END OF CSV CONTEXT ===";

            return implode("\n", $lines);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Build the full messages array to send to Groq.
     */
    private function buildMessages(array $history, string $csvContext, string $userMessage): array
    {
        $systemPrompt = <<<PROMPT
You are an expert data analyst assistant. The user has uploaded a CSV file and wants to ask questions about it.

Here is the CSV data you must analyse:

{$csvContext}

Instructions:
- Answer questions accurately based ONLY on the data provided above.
- When asked for top/bottom N items, compute them from the data sample given.
- If a question cannot be answered from the data provided, say so clearly.
- Format numbers nicely (e.g. 1,234 instead of 1234).
- Use bullet points or tables in Markdown when it improves clarity.
- Keep answers concise but complete.
- Respond in the same language the user uses (French or English).
- Never make up data that isn't in the context.
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Inject conversation history
        foreach ($history as $turn) {
            $messages[] = $turn;
        }

        // Current user message
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
     * Return the first N rows of a CSV as an array for preview.
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