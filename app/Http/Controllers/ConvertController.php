<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as PhpXlsxWriter;

class ConvertController extends Controller
{
    // Toon de converter pagina (form + resultaat).
    public function index()
    {
        return view('convert.index');
    }

    /**
     * Download een geconverteerd bestand als "attachment" (dus niet openen in browser).
     *
     * @param string $job  De job-id (uuid mapnaam)
     * @param string $file Bestandsnaam (bv. data.xml)
     */
    public function download(string $job, string $file)
    {
        // Security: nooit path traversal toelaten (../../etc/passwd)
        $file = basename($file);

        // Relatief pad binnen storage/app/public
        $rel = "converts/{$job}/{$file}";

        // Bestaat het bestand wel?
        if (!Storage::disk('public')->exists($rel)) {
            abort(404);
        }

        // Absoluut pad op disk
        $fullPath = Storage::disk('public')->path($rel);

        // Forceer download via Content-Disposition: attachment
        return response()->download($fullPath, $file, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    // Ontvang upload + target formaat, converteer en geef JSON response terug
    // (frontend toont preview + download link).
    public function convert(Request $request)
    {
        // 1) Validatie (max 20MB, toegelaten extensies)
        $request->validate([
            'file'   => 'required|file|mimes:csv,txt,xlsx,xls,json,xml|max:20480',
            'target' => 'required|in:xlsx,json,xml,txt,csv',
        ], [
            'file.max'   => 'File is too large. Maximum is 20MB.',
            'file.mimes' => 'Allowed files: CSV, TXT, XLSX, XLS, JSON, XML.',
        ]);

        // 2) Input info
        $target = (string) $request->input('target');
        $file   = $request->file('file');
        $inPath = $file->getRealPath();
        $ext    = strtolower($file->getClientOriginalExtension());


        // Normalize input ext zodat xls/xlsx allebei "xlsx" zijn (zelfde formaat blokkeren)
        $normalizedInput = match ($ext) {
            'xls', 'xlsx' => 'xlsx',
            default => $ext,
        };

        // 3) Backend guard: zelfde input -> target NIET toelaten (json->json, xml->xml, txt->txt, xlsx->xlsx)
        // Let op: xls->xlsx is wél nuttig, dus dat mag (ext = xls, target = xlsx => OK).
        if ($target === $normalizedInput) {
            return response()->json([
                'success' => false,
                'message' => 'Source and target format are the same. Please choose another output format.',
            ], 422);
        }

        // 4) Maak een unieke job directory voor output
        $jobId  = (string) Str::uuid();
        $dirRel = "converts/{$jobId}";
        Storage::disk('public')->makeDirectory($dirRel);

        // 5) Output bestandsnaam (zelfde basename, andere extensie)
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $outName  = $baseName . '.' . $target;
        $outRel   = "{$dirRel}/{$outName}";
        $outFull  = Storage::disk('public')->path($outRel);

        try {
            // Snelle streaming XLSX optie (grote CSV/TXT) als OpenSpout beschikbaar is.
            // Voordeel: geen mega memory use.
            if (
                $target === 'xlsx'
                && in_array($ext, ['csv', 'txt'], true)
                && class_exists('\\OpenSpout\\Writer\\XLSX\\Writer')
            ) {
                [$headers, $rowCount, $previewRows] = $this->writeXlsxStream($inPath, $outFull);

                return response()->json([
                    'success' => true,
                    'job_id'  => $jobId,
                    'target'  => $target,
                    'download_url' => route('convert.download', ['job' => $jobId, 'file' => $outName]),
                    'summary' => [
                        'columns' => count($headers),
                        'rows'    => $rowCount,
                        'file'    => $outName,
                    ],
                    'preview' => [
                        'headers' => $headers,
                        'rows'    => $previewRows,
                    ],
                ]);
            }

            // 6) Lees input (csv/txt/xlsx/xls/json/xml) naar: headers + rows (array of associative arrays)
            [$headers, $rows] = $this->readInput($ext, $inPath);

            // 7) Als er geen kolommen zijn: toon nette fout (bv. JSON structuur niet ok)
            if (empty($headers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The uploaded file seems empty or has no columns.',
                ], 422);
            }

            // 8) Schrijf output in gekozen formaat
            if ($target === 'xlsx') {
                $this->writeXlsxFromRows($outFull, $headers, $rows);
            } elseif ($target === 'json') {
                $this->writeJson($outFull, $rows);
            } elseif ($target === 'xml') {
                $this->writeXml($outFull, $headers, $rows);
            } elseif ($target === 'txt') {
                $this->writeTxt($outFull, $headers, $rows);
            } elseif ($target === 'csv') {
                $this->writeCsv($outFull, $headers, $rows);
            }

            // 9) Success response: summary + preview + download URL
            return response()->json([
                'success' => true,
                'job_id'  => $jobId,
                'target'  => $target,
                'download_url' => route('convert.download', ['job' => $jobId, 'file' => $outName]),
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
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Convert failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Router: kies de juiste reader op basis van extensie.
    private function readInput(string $ext, string $path): array
    {
        return match ($ext) {
            'csv', 'txt'  => $this->readCsv($path),
            'xlsx', 'xls' => $this->readSpreadsheet($path),
            'json'        => $this->readJson($path),
            'xml'         => $this->readXml($path),
            default       => [[], []],
        };
    }

    // CSV/TXT reader (auto-detect delimiter tussen , en ;)
    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) return [[], []];

        // Kijk naar de eerste lijn om delimiter te raden
        $firstLine = fgets($fh);
        if ($firstLine === false) return [[], []];

        $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($fh);

        // Headers = eerste rij
        $headers = fgetcsv($fh, 0, $delim);
        if (!$headers) return [[], []];

        $headers = array_map(fn($h) => trim((string) $h), $headers);

        // Data rows -> assoc arrays
        $rows = [];
        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
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

    // XLSX/XLS reader via PhpSpreadsheet (leest volledige file in memory).
    private function readSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        // Rij 1 = headers
        $headerRow = $sheet->rangeToArray("A1:{$highestCol}1", null, true, false)[0] ?? [];
        $headers = array_map(fn($h) => trim((string) $h), $headerRow);

        // Rij 2..N = data
        $rows = [];
        for ($r = 2; $r <= $highestRow; $r++) {
            $vals = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, false)[0] ?? [];
            $vals = array_pad($vals, count($headers), null);

            $assoc = [];
            foreach ($headers as $i => $h) {
                $assoc[$h] = $vals[$i] ?? null;
            }
            $rows[] = $assoc;
        }

        // Memory cleanup
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [$headers, $rows];
    }

    /**
     * JSON reader.
     * Ondersteunt:
     *  - [ {..}, {..} ]  (array of objects)
     *  - { "rows": [ {..}, ... ] }
     *  - { "customers": [ {..}, ... ] }  (we zoeken automatisch de eerste array-of-objects)
     */
    private function readJson(string $path): array
    {
        $raw = file_get_contents($path);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return [[], []];
        }

        // Case: { rows: [...] }
        if (isset($data['rows']) && is_array($data['rows'])) {
            $data = $data['rows'];
        }

        // Case: { customers: [...] } of andere wrapper keys
        if (!array_is_list($data)) {
            foreach ($data as $v) {
                if (is_array($v) && isset($v[0]) && is_array($v[0])) {
                    $data = $v;
                    break;
                }
            }
        }

        // Moet uiteindelijk een lijst zijn van objecten/associative arrays
        if (!isset($data[0]) || !is_array($data[0])) {
            return [[], []];
        }

        $headers = array_keys($data[0]);

        $rows = [];
        foreach ($data as $r) {
            $assoc = [];
            foreach ($headers as $h) {
                $assoc[$h] = $r[$h] ?? null;
            }
            $rows[] = $assoc;
        }

        return [$headers, $rows];
    }

    // XML reader (verwacht iets als <rows><row>...</row></rows> of vergelijkbaar)
    // We lezen elk child node als "row".
    private function readXml(string $path): array
    {
        $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) return [[], []];

        $rows = [];
        $headers = [];

        foreach ($xml->children() as $rowNode) {
            $assoc = [];
            foreach ($rowNode->children() as $col) {
                $k = $col->getName();
                $v = (string) $col;
                $assoc[$k] = $v;
                $headers[] = $k;
            }
            if (!empty($assoc)) {
                $rows[] = $assoc;
            }
        }

        $headers = array_values(array_unique($headers));

        // Normaliseer: elke rij moet alle headers hebben
        $normRows = [];
        foreach ($rows as $r) {
            $assoc = [];
            foreach ($headers as $h) {
                $assoc[$h] = $r[$h] ?? null;
            }
            $normRows[] = $assoc;
        }

        return [$headers, $normRows];
    }

    // Schrijf JSON output (pretty).
    private function writeJson(string $outFull, array $rows): void
    {
        file_put_contents(
            $outFull,
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    // Schrijf TXT output als TSV (tab-separated).
    private function writeTxt(string $outFull, array $headers, array $rows): void
    {
        $fh = fopen($outFull, 'w');

        // Eerste lijn: headers
        fputcsv($fh, $headers, "\t");

        // Data lijnen
        foreach ($rows as $r) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $r[$h] ?? '';
            }
            fputcsv($fh, $line, "\t");
        }

        fclose($fh);
    }


    // XML tags mogen niet zomaar eender welke chars bevatten.
    // We vervangen vreemde tekens door underscores en fixen tags die met een cijfer starten.
    private function sanitizeXmlTag(string $name): string
    {
        $tag = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        if ($tag === '' || is_numeric($tag[0] ?? '')) {
            $tag = 'col_' . $tag;
        }
        return $tag;
    }


    // Zorg dat XML value geldig UTF-8 is en geen verboden control chars bevat.
    private function sanitizeXmlValue($value): string
    {
        $s = (string) ($value ?? '');

        // Als encoding kapot is: strip ongeldige bytes
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = iconv('UTF-8', 'UTF-8//IGNORE', $s) ?: '';
        }

        // Verwijder verboden control characters voor XML 1.0
        return preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $s
        ) ?? '';
    }

    // Schrijf XML output: <rows><row>...</row></rows>
    private function writeXml(string $outFull, array $headers, array $rows): void
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('rows');
        $doc->appendChild($root);

        foreach ($rows as $r) {
            $rowEl = $doc->createElement('row');
            $root->appendChild($rowEl);

            foreach ($headers as $h) {
                $tag = $this->sanitizeXmlTag($h);
                $val = $this->sanitizeXmlValue($r[$h] ?? '');

                $colEl = $doc->createElement($tag);
                $colEl->appendChild($doc->createTextNode($val));
                $rowEl->appendChild($colEl);
            }
        }

        $doc->save($outFull);
    }

    // Schrijf XLSX output via PhpSpreadsheet.
    private function writeXlsxFromRows(string $outFull, array $headers, array $rows): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header rij
        $sheet->fromArray($headers, null, 'A1');

        // Data rijen
        $r = 2;
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $row[$h] ?? '';
            }
            $sheet->fromArray($line, null, 'A' . $r);
            $r++;
        }

        $writer = new PhpXlsxWriter($spreadsheet);
        $writer->save($outFull);

        // Memory cleanup
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }


    // Grote CSV/TXT -> XLSX streaming via OpenSpout.
    // Return: [headers, rowCount, previewRows]
    private function writeXlsxStream(string $csvPath, string $outFull): array
    {
        $fh = fopen($csvPath, 'r');
        if (!$fh) {
            throw new \RuntimeException("Cannot open CSV/TXT");
        }

        $firstLine = fgets($fh);
        if ($firstLine === false) {
            throw new \RuntimeException("Empty CSV/TXT");
        }

        $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($fh);

        $headers = fgetcsv($fh, 0, $delim);
        if (!$headers) {
            throw new \RuntimeException("CSV/TXT has no headers");
        }
        $headers = array_map(fn($h) => trim((string) $h), $headers);

        $options = new \OpenSpout\Writer\XLSX\Options();
        $writer  = new \OpenSpout\Writer\XLSX\Writer($options);
        $writer->openToFile($outFull);

        // Header row
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($headers));

        $rowCount = 0;
        $preview  = [];

        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            $row = array_pad($row, count($headers), '');

            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($row));
            $rowCount++;

            // Kleine preview voor UI (eerste 5 rows)
            if (count($preview) < 5) {
                $assoc = [];
                foreach ($headers as $i => $h) {
                    $assoc[$h] = $row[$i] ?? '';
                }
                $preview[] = $assoc;
            }
        }

        fclose($fh);
        $writer->close();

        return [$headers, $rowCount, $preview];
    }

     // Schrijft rows naar een CSV-bestand (komma-gescheiden).
    private function writeCsv(string $outFull, array $headers, array $rows): void
    {
        $fh = fopen($outFull, 'w');
        if (!$fh) {
            throw new \RuntimeException("Cannot write CSV output file.");
        }

        // Headers
        fputcsv($fh, $headers, ',');

        // Data rows
        foreach ($rows as $r) {
            $line = [];
            foreach ($headers as $h) {
                $v = $r[$h] ?? '';

                // Veilig naar string (voor het geval er null/array/obj zou zitten)
                if (is_array($v) || is_object($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                }

                $line[] = $v;
            }
            fputcsv($fh, $line, ',');
        }

        fclose($fh);
    }
}
