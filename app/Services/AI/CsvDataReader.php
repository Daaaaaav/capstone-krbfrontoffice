<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CsvDataReader
{
    public const REQUIRED_COLUMNS = [
        'date',
        'visitors',
        'docs_packages_received',
        'docs_packages_sent',
        'offline_room_bookings',
        'online_room_bookings',
        'vehicle_bookings',
    ];

    public const SERVER_CSV_PATH = 'lstm/krb_historical_data.csv';
    public const DISK = 'local';
    public const UPLOAD_PATH = 'lstm/uploads';

    private const PARSE_TTL = 3600;

    public function resolveServerCsvPath(): string
    {
        $path = Storage::disk(self::DISK)->path(self::SERVER_CSV_PATH);
        if (file_exists($path)) {
            return $path;
        }

        $fallback = base_path('docs/krb_historical_data.csv');
        if (file_exists($fallback)) {
            return $fallback;
        }

        return $path;
    }

    public function readServerCsv(string $metric = 'visitors'): array
    {
        $path = $this->resolveServerCsvPath();

        if (!file_exists($path)) {
            throw new \RuntimeException(
                'Server CSV not found at: ' . $path . '. ' .
                'Run docs/generate_historical_csv.py and copy the output to storage/app/private/lstm/.'
            );
        }

        return $this->parseCsvCached($path, $metric);
    }

    public function readUploadedCsv(string $storagePath, string $metric = 'visitors'): array
    {
        $path = Storage::disk(self::DISK)->path($storagePath);

        if (!file_exists($path)) {
            throw new \RuntimeException('Uploaded CSV not found: ' . $storagePath);
        }

        return $this->parseCsvCached($path, $metric);
    }

    public function readServerCsvColumnsSummed(array $metrics): array
    {
        $path = $this->resolveServerCsvPath();

        if (!file_exists($path)) {
            throw new \RuntimeException(
                'Server CSV not found at: ' . $path . '. ' .
                'Run docs/generate_historical_csv.py and copy the output to storage/app/private/lstm/.'
            );
        }

        return $this->parseCsvMultiColumnCached($path, $metrics);
    }

    public function readUploadedCsvColumnsSummed(string $storagePath, array $metrics): array
    {
        $path = Storage::disk(self::DISK)->path($storagePath);

        if (!file_exists($path)) {
            throw new \RuntimeException('Uploaded CSV not found: ' . $storagePath);
        }

        return $this->parseCsvMultiColumnCached($path, $metrics);
    }

    public function validateColumns(string $storagePath): array
    {
        $path = file_exists($storagePath)
            ? $storagePath
            : Storage::disk(self::DISK)->path($storagePath);

        if (!file_exists($path)) {
            $fallback = base_path('docs/' . basename($storagePath));
            if (file_exists($fallback)) {
                $path = $fallback;
            } else {
                return self::REQUIRED_COLUMNS;
            }
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return self::REQUIRED_COLUMNS;
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if ($headers === false) {
            return self::REQUIRED_COLUMNS;
        }

        $headers = array_map('trim', array_map('strtolower', $headers));
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $col) {
            if (!in_array($col, $headers, true)) {
                $missing[] = $col;
            }
        }

        return $missing;
    }

    public function serverCsvInfo(): array
    {
        $path = Storage::disk(self::DISK)->path(self::SERVER_CSV_PATH);

        if (!file_exists($path)) {
            return ['rows' => 0, 'start' => null, 'end' => null, 'error' => 'CSV file not found'];
        }

        // Cache CSV info based on file modification time for performance
        $mtime = (int) filemtime($path);
        $cacheKey = 'csv.server_info.' . md5($path) . '.' . $mtime;

        return Cache::remember($cacheKey, self::PARSE_TTL, function () use ($path) {
            try {
                $handle = fopen($path, 'r');
                if ($handle === false) {
                    throw new \RuntimeException("Cannot open CSV: {$path}");
                }

                $rawHeaders = fgetcsv($handle);
                if ($rawHeaders === false) {
                    fclose($handle);
                    return ['rows' => 0, 'start' => null, 'end' => null];
                }

                $headers = array_map('trim', array_map('strtolower', $rawHeaders));
                $dateIdx = array_search('date', $headers, true);

                if ($dateIdx === false) {
                    fclose($handle);
                    return ['rows' => 0, 'start' => null, 'end' => null];
                }

                $rowCount  = 0;
                $firstDate = null;
                $lastDate  = null;

                while (($row = fgetcsv($handle)) !== false) {
                    if (!isset($row[$dateIdx])) {
                        continue;
                    }

                    $date = trim($row[$dateIdx]);

                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                        continue;
                    }

                    $rowCount++;

                    if ($firstDate === null) {
                        $firstDate = $date;
                    }

                    $lastDate = $date;
                }

                fclose($handle);

                if ($rowCount === 0) {
                    return ['rows' => 0, 'start' => null, 'end' => null];
                }

                Log::info('CsvDataReader: serverCsvInfo() computed', [
                    'rows'  => $rowCount,
                    'start' => $firstDate,
                    'end'   => $lastDate,
                ]);

                return [
                    'rows'  => $rowCount,
                    'start' => $firstDate,
                    'end'   => $lastDate,
                ];
            } catch (\Throwable $e) {
                Log::error('CsvDataReader: serverCsvInfo() failed', ['error' => $e->getMessage()]);
                return ['rows' => 0, 'start' => null, 'end' => null, 'error' => $e->getMessage()];
            }
        });
    }

    private function parseCsvCached(string $absolutePath, string $metric): array
    {
        $mtime    = (int) filemtime($absolutePath);
        $cacheKey = 'csv.' . md5($absolutePath) . '.' . $metric . '.' . $mtime;

        return Cache::remember($cacheKey, self::PARSE_TTL, function () use ($absolutePath, $metric) {
            Log::info('CsvDataReader: cache miss — parsing CSV', [
                'path'   => basename($absolutePath),
                'metric' => $metric,
            ]);
            return $this->parseCsv($absolutePath, $metric);
        });
    }

    private function parseCsvMultiColumnCached(string $absolutePath, array $metrics): array
    {
        sort($metrics);
        $mtime    = (int) filemtime($absolutePath);
        $metaKey  = implode('+', $metrics);
        $cacheKey = 'csv.' . md5($absolutePath) . '.' . md5($metaKey) . '.' . $mtime;

        return Cache::remember($cacheKey, self::PARSE_TTL, function () use ($absolutePath, $metrics) {
            Log::info('CsvDataReader: cache miss — parsing CSV (multi-column)', [
                'path'    => basename($absolutePath),
                'metrics' => implode('+', $metrics),
            ]);
            return $this->parseCsvMultiColumn($absolutePath, $metrics);
        });
    }

    private function parseCsv(string $absolutePath, string $metric): array
    {
        if (!in_array($metric, self::REQUIRED_COLUMNS, true) || $metric === 'date') {
            throw new \InvalidArgumentException(
                "Invalid metric '{$metric}'. Must be one of: " .
                implode(', ', array_diff(self::REQUIRED_COLUMNS, ['date']))
            );
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV: {$absolutePath}");
        }

        $rawHeaders = fgetcsv($handle);
        if ($rawHeaders === false) {
            fclose($handle);
            throw new \RuntimeException("CSV is empty: {$absolutePath}");
        }

        $headers = array_map('trim', array_map('strtolower', $rawHeaders));

        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $col) {
            if (!in_array($col, $headers, true)) {
                $missing[] = $col;
            }
        }
        if (!empty($missing)) {
            fclose($handle);
            throw new \InvalidArgumentException(
                'CSV is missing required columns: ' . implode(', ', $missing)
            );
        }

        $dateIdx   = array_search('date',  $headers, true);
        $metricIdx = array_search($metric, $headers, true);

        $timeSeries = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) <= max($dateIdx, $metricIdx)) {
                continue;
            }

            $date  = trim($row[$dateIdx]);
            $count = trim($row[$metricIdx]);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $timeSeries[] = [
                'date'  => $date,
                'count' => max(0, (int) $count),
            ];
        }

        fclose($handle);
        usort($timeSeries, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $timeSeries;
    }

    private function parseCsvMultiColumn(string $absolutePath, array $metrics): array
    {
        $validMetrics = array_diff(self::REQUIRED_COLUMNS, ['date']);
        foreach ($metrics as $metric) {
            if (!in_array($metric, $validMetrics, true)) {
                throw new \InvalidArgumentException(
                    "Invalid metric '{$metric}'. Must be one of: " . implode(', ', $validMetrics)
                );
            }
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV: {$absolutePath}");
        }

        $rawHeaders = fgetcsv($handle);
        if ($rawHeaders === false) {
            fclose($handle);
            throw new \RuntimeException("CSV is empty: {$absolutePath}");
        }

        $headers = array_map('trim', array_map('strtolower', $rawHeaders));
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $col) {
            if (!in_array($col, $headers, true)) {
                $missing[] = $col;
            }
        }
        if (!empty($missing)) {
            fclose($handle);
            throw new \InvalidArgumentException(
                'CSV is missing required columns: ' . implode(', ', $missing)
            );
        }

        $dateIdx    = array_search('date', $headers, true);
        $metricIdxs = [];
        foreach ($metrics as $m) {
            $metricIdxs[] = array_search($m, $headers, true);
        }
        $maxIdx     = max($dateIdx, ...$metricIdxs);
        $timeSeries = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) <= $maxIdx) {
                continue;
            }

            $date = trim($row[$dateIdx]);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $count = 0;
            foreach ($metricIdxs as $idx) {
                $count += max(0, (int) trim($row[$idx]));
            }

            $timeSeries[] = [
                'date'  => $date,
                'count' => $count,
            ];
        }

        fclose($handle);
        usort($timeSeries, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $timeSeries;
    }

    public function getCsvSourceMetadata(): array
    {
        $info = $this->serverCsvInfo();
        return [
            'type'        => 'server_csv',
            'label'       => 'Server Historical CSV (krb_historical_data.csv)',
            'file'        => 'krb_historical_data.csv',
            'description' => 'Retrieved from server-side historical time-series dataset (krb_historical_data.csv)',
            'total_rows'  => $info['rows'] ?? 0,
            'start_date'  => $info['start'] ?? null,
            'end_date'    => $info['end'] ?? null,
        ];
    }

    public function getHistoricalRows(?string $startDate = null, ?string $endDate = null, ?string $weekday = null): array
    {
        $path = $this->resolveServerCsvPath();
        if (!file_exists($path)) {
            return [];
        }

        $mtime = (int) filemtime($path);
        $cacheKey = 'csv.all_rows.' . md5($path) . '.' . $mtime;

        $allRows = Cache::remember($cacheKey, self::PARSE_TTL, function () use ($path) {
            $handle = fopen($path, 'r');
            if ($handle === false) {
                return [];
            }

            $rawHeaders = fgetcsv($handle);
            if ($rawHeaders === false) {
                fclose($handle);
                return [];
            }

            $headers = array_map('trim', array_map('strtolower', $rawHeaders));
            $dateIdx = array_search('date', $headers, true);
            $dayNameIdx = array_search('day_name', $headers, true);
            $visitorsIdx = array_search('visitors', $headers, true);
            $docRecIdx = array_search('docs_packages_received', $headers, true);
            $docSentIdx = array_search('docs_packages_sent', $headers, true);
            $offRoomIdx = array_search('offline_room_bookings', $headers, true);
            $onRoomIdx = array_search('online_room_bookings', $headers, true);
            $vehIdx = array_search('vehicle_bookings', $headers, true);

            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                if (!isset($row[$dateIdx])) {
                    continue;
                }
                $date = trim($row[$dateIdx]);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }

                $dayName = $dayNameIdx !== false && isset($row[$dayNameIdx]) ? trim($row[$dayNameIdx]) : date('l', strtotime($date));

                $rows[] = [
                    'date'                   => $date,
                    'day_name'               => $dayName,
                    'visitors'               => $visitorsIdx !== false && isset($row[$visitorsIdx]) ? max(0, (int) $row[$visitorsIdx]) : 0,
                    'docs_packages_received' => $docRecIdx !== false && isset($row[$docRecIdx]) ? max(0, (int) $row[$docRecIdx]) : 0,
                    'docs_packages_sent'     => $docSentIdx !== false && isset($row[$docSentIdx]) ? max(0, (int) $row[$docSentIdx]) : 0,
                    'offline_room_bookings'  => $offRoomIdx !== false && isset($row[$offRoomIdx]) ? max(0, (int) $row[$offRoomIdx]) : 0,
                    'online_room_bookings'   => $onRoomIdx !== false && isset($row[$onRoomIdx]) ? max(0, (int) $row[$onRoomIdx]) : 0,
                    'room_bookings'          => ($offRoomIdx !== false && isset($row[$offRoomIdx]) ? max(0, (int) $row[$offRoomIdx]) : 0)
                                              + ($onRoomIdx !== false && isset($row[$onRoomIdx]) ? max(0, (int) $row[$onRoomIdx]) : 0),
                    'vehicle_bookings'       => $vehIdx !== false && isset($row[$vehIdx]) ? max(0, (int) $row[$vehIdx]) : 0,
                ];
            }

            fclose($handle);
            usort($rows, fn($a, $b) => strcmp($a['date'], $b['date']));
            return $rows;
        });

        // Filter rows in memory
        $filtered = [];
        $weekdayFilter = $weekday ? strtolower($weekday) : null;

        foreach ($allRows as $r) {
            if ($startDate !== null && $r['date'] < $startDate) {
                continue;
            }
            if ($endDate !== null && $r['date'] > $endDate) {
                continue;
            }
            if ($weekdayFilter !== null && strtolower($r['day_name']) !== $weekdayFilter) {
                continue;
            }
            $filtered[] = $r;
        }

        return $filtered;
    }

    public function getWeekdayAverageFromCsv(string $metric, string $weekday, int|string $year): array
    {
        $metricKey = match (strtolower($metric)) {
            'vehicle', 'vehicles', 'vehicle_booking', 'vehicle_bookings' => 'vehicle_bookings',
            'room', 'rooms', 'room_booking', 'room_bookings' => 'room_bookings',
            'offline_room_bookings' => 'offline_room_bookings',
            'online_room_bookings' => 'online_room_bookings',
            'visitor', 'visitors', 'guest', 'guests' => 'visitors',
            'delivery', 'deliveries', 'docs_packages_received', 'package', 'packages' => 'docs_packages_received',
            'docs_packages_sent' => 'docs_packages_sent',
            default => 'vehicle_bookings',
        };

        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        $rows = $this->getHistoricalRows($startDate, $endDate, $weekday);

        $rowCount = count($rows);
        if ($rowCount === 0) {
            return [
                'success'                       => false,
                'error'                         => "No historical CSV rows found for {$weekday} in year {$year}.",
                'metric'                        => $metricKey,
                'weekday'                       => ucfirst($weekday),
                'year'                          => (int) $year,
                'period_count'                  => 0,
                'total_metric_value'            => 0,
                'average'                       => 0.0,
                'zero_value_period_count'       => 0,
                'active_period_count'           => 0,
                'sources'                       => [$this->getCsvSourceMetadata()],
                'text'                          => "No historical CSV records found for " . ucfirst($weekday) . " in {$year}.",
            ];
        }

        $totalVal = 0;
        $zeroCount = 0;
        $activeCount = 0;

        foreach ($rows as $r) {
            $val = (int) ($r[$metricKey] ?? 0);
            $totalVal += $val;
            if ($val === 0) {
                $zeroCount++;
            } else {
                $activeCount++;
            }
        }

        $average = round($totalVal / $rowCount, 2);

        $metricLabel = match ($metricKey) {
            'vehicle_bookings'       => 'vehicle bookings',
            'room_bookings'          => 'room bookings',
            'offline_room_bookings'  => 'offline room bookings',
            'online_room_bookings'   => 'online room bookings',
            'visitors'               => 'visitors',
            'docs_packages_received' => 'packages received',
            'docs_packages_sent'     => 'packages sent',
            default                  => $metricKey,
        };

        $pluralWeekday = ucfirst($weekday) . 's';
        $text = "The historical average was **{$average} {$metricLabel} per {$weekday}** in {$year} (from {$rowCount} recorded {$pluralWeekday} in the historical dataset, including {$zeroCount} {$pluralWeekday} with 0 {$metricLabel}).";

        return [
            'success'                       => true,
            'source_type'                   => 'server_csv',
            'metric'                        => $metricKey,
            'weekday'                       => ucfirst($weekday),
            'year'                          => (int) $year,
            'period_count'                  => $rowCount,
            'total_metric_value'            => $totalVal,
            'average'                       => $average,
            'zero_value_period_count'       => $zeroCount,
            'active_period_count'           => $activeCount,
            'included_zero_booking_periods' => true,
            'calculation'                   => [
                'formula'     => "total_metric_value / period_count",
                'numerator'   => $totalVal,
                'denominator' => $rowCount,
                'result'      => $average,
            ],
            'sources'                       => [$this->getCsvSourceMetadata()],
            'text'                          => $text,
        ];
    }
}
