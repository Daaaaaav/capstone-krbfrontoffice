<?php

namespace App\Services\AI;

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

    public function readServerCsv(string $metric = 'visitors'): array
    {
        $path = Storage::disk(self::DISK)->path(self::SERVER_CSV_PATH);

        if (!file_exists($path)) {
            throw new \RuntimeException(
                'Server CSV not found at: ' . $path . '. ' .
                'Run docs/generate_historical_csv.py and copy the output to storage/app/private/lstm/.'
            );
        }

        return $this->parseCsv($path, $metric);
    }

    public function readUploadedCsv(string $storagePath, string $metric = 'visitors'): array
    {
        $path = Storage::disk(self::DISK)->path($storagePath);

        if (!file_exists($path)) {
            throw new \RuntimeException('Uploaded CSV not found: ' . $storagePath);
        }

        return $this->parseCsv($path, $metric);
    }

    public function readServerCsvColumnsSummed(array $metrics): array
    {
        $path = Storage::disk(self::DISK)->path(self::SERVER_CSV_PATH);

        if (!file_exists($path)) {
            throw new \RuntimeException(
                'Server CSV not found at: ' . $path . '. ' .
                'Run docs/generate_historical_csv.py and copy the output to storage/app/private/lstm/.'
            );
        }

        return $this->parseCsvMultiColumn($path, $metrics);
    }

    public function readUploadedCsvColumnsSummed(string $storagePath, array $metrics): array
    {
        $path = Storage::disk(self::DISK)->path($storagePath);

        if (!file_exists($path)) {
            throw new \RuntimeException('Uploaded CSV not found: ' . $storagePath);
        }

        return $this->parseCsvMultiColumn($path, $metrics);
    }

    public function validateColumns(string $storagePath): array
    {
        $path = Storage::disk(self::DISK)->path($storagePath);

        if (!file_exists($path)) {
            return self::REQUIRED_COLUMNS;
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
        try {
            $rows = $this->readServerCsv('visitors');
            if (empty($rows)) {
                return ['rows' => 0, 'start' => null, 'end' => null];
            }
            return [
                'rows'  => count($rows),
                'start' => $rows[0]['date'],
                'end'   => $rows[count($rows) - 1]['date'],
            ];
        } catch (\Throwable $e) {
            return ['rows' => 0, 'start' => null, 'end' => null, 'error' => $e->getMessage()];
        }
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

        Log::info('CsvDataReader: parsed CSV', [
            'path'   => basename($absolutePath),
            'metric' => $metric,
            'rows'   => count($timeSeries),
        ]);

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

        $dateIdx = array_search('date', $headers, true);
        $metricIdxs = [];
        foreach ($metrics as $m) {
            $metricIdxs[] = array_search($m, $headers, true);
        }
        $maxIdx = max($dateIdx, ...$metricIdxs);
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

        Log::info('CsvDataReader: parsed CSV (multi-column)', [
            'path'    => basename($absolutePath),
            'metrics' => implode('+', $metrics),
            'rows'    => count($timeSeries),
        ]);

        return $timeSeries;
    }
}
