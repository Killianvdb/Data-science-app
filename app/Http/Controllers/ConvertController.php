<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Common\Entity\Row;

class ConvertController extends Controller
{
    public function index()
    {
        return view('convert.index');
    }

    public function convert(Request $request)
    {
        $request->validate([
            'file'   => 'required|file|mimes:csv,txt|max:20480',
            'target' => 'required|in:xlsx,json,xml,txt',
        ], [
            'file.max' => 'File is too large. Maximum is 20MB.',
            'file.mimes' => 'Only CSV or TXT files are allowed.',
        ]);

        $target = $request->input('target');
        $file   = $request->file('file');

        // 1) Lees CSV (simpel + robuust genoeg)
        [$headers, $rows] = $this->readCsv($file->getRealPath());

        if (empty($headers)) {
            return response()->json([
                'success' => false,
                'message' => 'CSV lijkt geen header/kolommen te bevatten.',
            ], 422);
        }

        // 2) Output pad (public storage)
        $jobId = (string) Str::uuid();
        $dirRel = "converts/{$jobId}";
        Storage::disk('public')->makeDirectory($dirRel);

        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $outName  = $baseName . '.' . $target;
        $outRel   = "{$dirRel}/{$outName}";
        $outFull  = Storage::disk('public')->path($outRel);

        // 3) Converteer
        try {
            if ($target === 'json') {
                $this->writeJson($outFull, $headers, $rows);
            } elseif ($target === 'xml') {
                $this->writeXml($outFull, $headers, $rows);
            } elseif ($target === 'txt') {
                $this->writeTxt($outFull, $headers, $rows); // TSV
            } elseif ($target === 'xlsx') {
                $this->writeXlsx($outFull, $headers, $rows);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Convert failed: ' . $e->getMessage(),
            ], 500);
        }

        // 4) Response voor frontend
        $downloadUrl = asset('storage/' . $outRel);

        return response()->json([
            'success' => true,
            'job_id'  => $jobId,
            'target'  => $target,
            'download_url' => $downloadUrl,
            'summary' => [
                'columns' => count($headers),
                'rows'    => count($rows),
                'file'    => $outName,
            ],
            'preview' => [
                'headers' => $headers,
                'rows'    => array_slice($rows, 0, 5),
            ],
        ]);
    }

    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) return [[], []];

        // probeer delimiter auto (comma vs semicolon)
        $firstLine = fgets($fh);
        if ($firstLine === false) return [[], []];

        $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($fh);

        $headers = fgetcsv($fh, 0, $delim);
        if (!$headers) return [[], []];

        // trim headers
        $headers = array_map(fn($h) => trim((string)$h), $headers);

        $rows = [];
        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            // maak lengte gelijk aan headers
            $row = array_pad($row, count($headers), null);
            $assoc = [];
            foreach ($headers as $i => $h) {
                $assoc[$h] = $row[$i] ?? null;
            }
            $rows[] = $assoc;
        }

        fclose($fh);
        return [$headers, $rows];
    }

    private function writeJson(string $outFull, array $headers, array $rows): void
    {
        file_put_contents($outFull, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function writeTxt(string $outFull, array $headers, array $rows): void
    {
        // TSV
        $fh = fopen($outFull, 'w');
        fputcsv($fh, $headers, "\t");
        foreach ($rows as $r) {
            $line = [];
            foreach ($headers as $h) $line[] = $r[$h] ?? '';
            fputcsv($fh, $line, "\t");
        }
        fclose($fh);
    }

    private function writeXml(string $outFull, array $headers, array $rows): void
    {
        $xml = new \SimpleXMLElement('<rows/>');
        foreach ($rows as $r) {
            $item = $xml->addChild('row');
            foreach ($headers as $h) {
                $tag = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $h);
                if ($tag === '' || is_numeric($tag[0] ?? '')) $tag = 'col_' . $tag;
                $val = (string) ($r[$h] ?? '');
                $item->addChild($tag, htmlspecialchars($val));
            }
        }
        $xml->asXML($outFull);
    }

    private function writeXlsx(string $outFull, array $headers, array $rows): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new \RuntimeException("PhpSpreadsheet ontbreekt. Run: composer require phpoffice/phpspreadsheet");
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // headers (rij 1)
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $h);
        }

        // data (vanaf rij 2)
        $rIndex = 2;
        foreach ($rows as $row) {
            foreach ($headers as $i => $h) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue($col . $rIndex, $row[$h] ?? '');
            }
            $rIndex++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($outFull);
    }
}
