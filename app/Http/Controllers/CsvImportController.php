<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CsvImportController extends Controller
{
    public function form()
    {
        return view('csv.import');
    }

    private function detectDelimiter(string $firstLine): string
    {
        $candidates = [';', ',', "\t"];
        $best = ',';
        $bestCount = -1;

        foreach ($candidates as $d) {
            $count = substr_count($firstLine, $d);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $d;
            }
        }

        return $best;
    }

    private function normalizeHeader(string $h): string
    {
        $h = trim($h);
        $h = preg_replace('/\s+/', '_', $h);
        $h = preg_replace('/[^a-zA-Z0-9_]/', '', $h);
        return mb_strtolower($h);
    }

    private function parseCsvAssoc(string $tmpPath, int $maxRows = 3000): array
    {
        $file = new \SplFileObject($tmpPath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $firstLine = $file->fgets();
        $delimiter = $this->detectDelimiter($firstLine);

        $file->rewind();
        $file->setCsvControl($delimiter);

        $headers = null;
        $rows = [];
        $count = 0;

        foreach ($file as $row) {
            if (!$row || (count($row) === 1 && $row[0] === null)) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn($h) => $this->normalizeHeader((string)($h ?? '')), $row);
                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $key) {
                if ($key === '') continue;
                $assoc[$key] = isset($row[$i]) ? trim((string)$row[$i]) : null;
            }

            $nonEmpty = false;
            foreach ($assoc as $v) {
                if ($v !== null && trim((string)$v) !== '') { $nonEmpty = true; break; }
            }
            if (!$nonEmpty) continue;

            $rows[] = $assoc;
            $count++;

            if ($count >= $maxRows) break;
        }

        return [$headers ?? [], $rows, $delimiter];
    }

private function parseTxtLikeCsv(string $tmpPath, int $maxRows = 3000): array
    {
        return $this->parseCsvAssoc($tmpPath, $maxRows);
    }

    private function parseJson(string $tmpPath, int $maxRows = 3000): array
    {
        $raw = file_get_contents($tmpPath);
        if ($raw === false) return [[], []];

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) return [[], []];

        $rows = [];

        if (is_array($data) && array_is_list($data)) {
            $rows = $data;
        } elseif (is_array($data)) {
            foreach ($data as $v) {
                if (is_array($v) && array_is_list($v)) {
                    $rows = $v;
                    break;
                }
            }
        }

        if (!is_array($rows) || count($rows) === 0) return [[], []];

        $assocRows = [];
        $headersSet = [];

        foreach (array_slice($rows, 0, $maxRows) as $r) {
            if (!is_array($r)) continue;

            if (array_is_list($r)) {
                $tmp = [];
                foreach ($r as $i => $val) $tmp["col_$i"] = $val;
                $r = $tmp;
            }

            $clean = [];
            foreach ($r as $k => $v) {
                $hk = $this->normalizeHeader((string)$k);
                if ($hk === '') continue;

                if (is_array($v) || is_object($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                }

                $clean[$hk] = is_null($v) ? null : trim((string)$v);
                $headersSet[$hk] = true;
            }

            if (count($clean)) $assocRows[] = $clean;
        }

        $headers = array_keys($headersSet);

        foreach ($assocRows as &$r) {
            foreach ($headers as $h) {
                if (!array_key_exists($h, $r)) $r[$h] = null;
            }
        }

        return [$headers, $assocRows];
    }

    private function parseXml(string $tmpPath, int $maxRows = 3000): array
    {
        $raw = file_get_contents($tmpPath);
        if ($raw === false) return [[], []];

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) return [[], []];


        $children = $xml->children();
        if (count($children) === 0) return [[], []];

        if (count($children) === 1) {
            $maybe = $children[0]->children();
            if (count($maybe) > 0) {
                $children = $maybe;
            }
        }

        $rows = [];
        $headersSet = [];

        $i = 0;
        foreach ($children as $node) {
            if ($i >= $maxRows) break;

            $r = [];

            foreach ($node->attributes() as $k => $v) {
                $hk = $this->normalizeHeader((string)$k);
                $r[$hk] = trim((string)$v);
                $headersSet[$hk] = true;
            }

            foreach ($node->children() as $k => $v) {
                $hk = $this->normalizeHeader((string)$k);
                if ($hk === '') continue;

                if (count($v->children()) > 0) {
                    $r[$hk] = json_encode($v, JSON_UNESCAPED_UNICODE);
                } else {
                    $r[$hk] = trim((string)$v);
                }


                $headersSet[$hk] = true;
            }

            if (count($r)) {
                $rows[] = $r;
                $i++;
            }
        }

        $headers = array_keys($headersSet);

        foreach ($rows as &$r) {
            foreach ($headers as $h) {
                if (!array_key_exists($h, $r)) $r[$h] = null;
            }
        }

        return [$headers, $rows];
    }

    private function makeHeadersUnique(array $headers): array
    {
        $seen = [];
        $out = [];

        foreach ($headers as $h) {
            $base = $h === '' ? 'col' : $h;
            if (!isset($seen[$base])) {
                $seen[$base] = 1;
                $out[] = $base;
            } else {
                $seen[$base]++;
                $out[] = $base . '_' . $seen[$base];
            }
        }
        return $out;
    }

    private function parseExcel(string $tmpPath, int $maxRows = 3000): array
    {
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true); // keys A,B,C...

        if (!$data || count($data) < 1) return [[], []];

        $firstRow = array_shift($data);
        $headers = [];
        $colKeys = array_keys($firstRow);

        foreach ($colKeys as $colKey) {
            $headers[$colKey] = $this->normalizeHeader((string)($firstRow[$colKey] ?? ''));
        }

        $allEmpty = true;
        foreach ($headers as $h) {
            if ($h !== '') { $allEmpty = false; break; }
        }
        if ($allEmpty) {
            foreach ($colKeys as $colKey) $headers[$colKey] = "col_" . strtolower($colKey);
        }

        $finalHeaders = [];
        
        foreach ($headers as $colKey => $h) {
            if ($h !== '') $finalHeaders[] = $h;
        }
        $finalHeaders = $this->makeHeadersUnique($finalHeaders);

        $rows = [];
        $count = 0;

        foreach ($data as $row) {
            if ($count >= $maxRows) break;

            $assoc = [];
            foreach ($headers as $colKey => $h) {
                if ($h === '') continue;
                $val = $row[$colKey] ?? null;
                $assoc[$h] = is_null($val) ? null : trim((string)$val);
            }

            $nonEmpty = false;
            foreach ($assoc as $v) {
                if ($v !== null && trim((string)$v) !== '') { $nonEmpty = true; break; }
            }
            if (!$nonEmpty) continue;

            $rows[] = $assoc;
            $count++;
        }

        return [$finalHeaders, $rows];
    }




    private function parseNumber(?string $value): ?float
    {
        if ($value === null) return null;
        $v = trim($value);
        if ($v === '') return null;

        $v = preg_replace('/[^\d,\.\-]/', '', $v);

        if (substr_count($v, ',') > 0 && substr_count($v, '.') > 0) {
            $v = str_replace(',', '', $v);
        } else {
            $v = str_replace(',', '.', $v);
        }

        if (!is_numeric($v)) return null;
        return (float)$v;
    }

    private function parseDate(?string $value): ?int
    {
        if ($value === null) return null;
        $v = trim($value);
        if ($v === '') return null;

        $formats = [
            'Y-m-d', 'Y/m/d',
            'd-m-Y', 'd/m/Y',
            'm-d-Y', 'm/d/Y',
            'Y-m-d H:i:s', 'Y/m/d H:i:s',
            'd/m/Y H:i:s', 'd-m-Y H:i:s',
            'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP',
        ];

        foreach ($formats as $f) {
            $dt = \DateTime::createFromFormat($f, $v);
            if ($dt instanceof \DateTime) {
                $errors = \DateTime::getLastErrors();
                if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                    return $dt->getTimestamp();
                }
            }
        }

        $ts = strtotime($v);
        if ($ts !== false) return $ts;

        return null;
    }

    private function inferColumnTypes(array $headers, array $rows): array
    {
        $stats = [];
        foreach ($headers as $h) {
            $stats[$h] = [
                'nonEmpty' => 0,
                'numeric' => 0,
                'date' => 0,
                'unique' => [],
                'maxLen' => 0,
            ];
        }

        $sampleLimit = min(count($rows), 800);

        for ($i = 0; $i < $sampleLimit; $i++) {
            $r = $rows[$i];
            foreach ($headers as $h) {
                $val = $r[$h] ?? null;
                $val = $val === null ? null : trim((string)$val);

                if ($val === null || $val === '') continue;

                $stats[$h]['nonEmpty']++;
                $stats[$h]['maxLen'] = max($stats[$h]['maxLen'], mb_strlen($val));

                if (count($stats[$h]['unique']) < 200) {
                    $stats[$h]['unique'][$val] = true;
                }

                if ($this->parseNumber($val) !== null) $stats[$h]['numeric']++;
                if ($this->parseDate($val) !== null) $stats[$h]['date']++;
            }
        }

        $types = [];
        foreach ($headers as $h) {
            $nonEmpty = max(1, $stats[$h]['nonEmpty']);
            $numericRatio = $stats[$h]['numeric'] / $nonEmpty;
            $dateRatio = $stats[$h]['date'] / $nonEmpty;
            $uniqueCount = count($stats[$h]['unique']);
            $maxLen = $stats[$h]['maxLen'];

            if ($dateRatio >= 0.75) {
                $types[$h] = 'date';
            } elseif ($numericRatio >= 0.85) {
                $types[$h] = 'numeric';
            } else {
                if ($uniqueCount <= 30 && $maxLen <= 60) {
                    $types[$h] = 'categorical';
                } else {
                    $types[$h] = 'text';
                }
            }
        }

        return $types;
    }

    private function makeHistogram(array $values, int $bins = 10): array
    {
        $values = array_values(array_filter($values, fn($v) => $v !== null));
        if (count($values) < 2) return [[], []];

        $min = min($values);
        $max = max($values);
        if ($min === $max) return [["{$min}"], [count($values)]];

        $step = ($max - $min) / $bins;
        $counts = array_fill(0, $bins, 0);
        $labels = [];

        for ($i=0; $i<$bins; $i++) {
            $a = $min + $i * $step;
            $b = $min + ($i + 1) * $step;
            $labels[] = round($a, 2) . ' - ' . round($b, 2);
        }

        foreach ($values as $v) {
            $idx = (int) floor(($v - $min) / $step);
            if ($idx >= $bins) $idx = $bins - 1;
            if ($idx < 0) $idx = 0;
            $counts[$idx]++;
        }

        return [$labels, $counts];
    }

    private function topCategories(array $rows, string $col, int $top = 12): array
    {
        $counts = [];
        foreach ($rows as $r) {
            $v = isset($r[$col]) ? trim((string)$r[$col]) : '';
            if ($v === '') $v = 'unknown';
            $counts[$v] = ($counts[$v] ?? 0) + 1;
        }
        arsort($counts);
        $labels = array_slice(array_keys($counts), 0, $top);
        $values = array_slice(array_values($counts), 0, $top);
        return [$labels, $values];
    }

    private function timeSeriesDailyAvg(array $rows, string $dateCol, string $numCol): array
    {
        $buckets = []; // 'YYYY-MM-DD' => ['sum'=>, 'n'=>]
        foreach ($rows as $r) {
            $ts = $this->parseDate($r[$dateCol] ?? null);
            $num = $this->parseNumber($r[$numCol] ?? null);
            if ($ts === null || $num === null) continue;

            $day = gmdate('Y-m-d', $ts);
            if (!isset($buckets[$day])) $buckets[$day] = ['sum' => 0, 'n' => 0];
            $buckets[$day]['sum'] += $num;
            $buckets[$day]['n'] += 1;
        }

        if (count($buckets) < 2) return [[], []];

        ksort($buckets);
        $labels = array_keys($buckets);
        $values = array_map(fn($x) => $x['n'] > 0 ? $x['sum'] / $x['n'] : 0, array_values($buckets));

        return [$labels, $values];
    }

    private function buildCharts(array $headers, array $rows, array $types): array
    {
        $charts = [];

        $numericCols = array_values(array_filter($headers, fn($h) => $types[$h] === 'numeric'));
        $categoricalCols = array_values(array_filter($headers, fn($h) => $types[$h] === 'categorical'));
        $dateCols = array_values(array_filter($headers, fn($h) => $types[$h] === 'date'));

        foreach (array_slice($numericCols, 0, 3) as $col) {
            $vals = [];
            foreach ($rows as $r) $vals[] = $this->parseNumber($r[$col] ?? null);
            [$labels, $counts] = $this->makeHistogram($vals, 10);

            if (count($labels)) {
                $charts[] = [
                    'title' => "Histogram: {$col}",
                    'type' => 'bar',
                    'labels' => $labels,
                    'data' => $counts,
                ];
            }
        }

        foreach (array_slice($categoricalCols, 0, 3) as $col) {
            [$labels, $values] = $this->topCategories($rows, $col, 12);
            if (count($labels)) {
                $charts[] = [
                    'title' => "Top categories: {$col}",
                    'type' => 'bar',
                    'labels' => $labels,
                    'data' => $values,
                ];
            }
        }

        if (count($dateCols) && count($numericCols)) {
            $dateCol = $dateCols[0];
            foreach (array_slice($numericCols, 0, 2) as $numCol) {
                [$labels, $values] = $this->timeSeriesDailyAvg($rows, $dateCol, $numCol);
                if (count($labels)) {
                    $charts[] = [
                        'title' => "Time series (daily avg): {$numCol} by {$dateCol}",
                        'type' => 'line',
                        'labels' => $labels,
                        'data' => $values,
                    ];
                }
            }
        }

        return $charts;
    }

    private function parseUploadedFile(Request $request, int $maxRows = 3000): array
    {
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $tmpPath = $file->getRealPath();

        return match ($ext) {
            'csv' => (function() use ($tmpPath, $maxRows) {
                [$headers, $rows] = $this->parseCsvAssoc($tmpPath, $maxRows);
                return [$headers, $rows];
            })(),
            'txt' => (function() use ($tmpPath, $maxRows) {
                [$headers, $rows] = $this->parseTxtLikeCsv($tmpPath, $maxRows);
                return [$headers, $rows];
            })(),
            'json' => $this->parseJson($tmpPath, $maxRows),
            'xml'  => $this->parseXml($tmpPath, $maxRows),
            'xlsx', 'xls' => $this->parseExcel($tmpPath, $maxRows),
            default => [[], []],
        };
    }


    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,json,xml,xlsx,xls','max:10240'],
        ]);

        [$headers, $rows] = $this->parseUploadedFile($request, 3000);

        if (count($headers) === 0 || count($rows) === 0) {
            return back()->with('error', 'Could not read the file or it is empty / in an unsupported format.');
        }

        $preview = array_slice($rows, 0, 30);
        $types = $this->inferColumnTypes($headers, $rows);
        $charts = $this->buildCharts($headers, $rows, $types);

        return view('csv.dashboard', compact('preview', 'types', 'charts'));
    }
}
