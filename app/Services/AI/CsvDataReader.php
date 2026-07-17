<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * CsvDataReader
 *
 * Reads a CSV file (either the bundled server CSV or a user-uploaded one)
 * and returns a time-series array in the format expected by LSTMClient:
 *   [ ['date' => 'Y-m-d', 'count' => int], ... ]
 *
 * Required CSV columns (order-independent, matched by header name):
 *   date, visitors, docs_packages_received, docs_packages_sent,
 *   offline_room_bookings, online_room_bookings, vehicle_bookings
 *
 * The 'metric' parameter selects which column becomes the 'count' value:
 *   'visitors'               → visitor traffic (default / LSTM Predictions page)
 *   'docs_packages_received' → received deliveries
 *   'docs_packages_sent'     → sent deliveries
 *   'offline_room_bookings'  → offline room bookings
 *   'online_room_bookings'   → online room bookings
 *   'vehicle_bookings'       → vehicle bookings
 */
class CsvDataReader
{
    /** Column names that must be present in any accepted CSV. */
    public const REQUIRED_COLUMNS = [
        'date',
        'visitors',
        'docs_packages_received',
        'docs_packages_sent',
        'offline_room_bookings',
        'online_room_bookings',
        'vehicle_bookings',
    ];

    /** Path to the bundled server CSV, relative to the private storage disk. */
    public const SERVER_CSV_PATH = 'lstm/krb_historical_data.csv';

    /** Storage disk used for both the server CSV and user uploads. */
    public const DISK = 'local';

    /** Storage path prefix for user-uploaded CSVs. */
    public const UPLOAD_PATH = 'lstm/uploads';

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Read the bundled server CSV and return a time series for the given metric.
     *
     * @param  string $metric  One of REQUIRED_COLUMNS (excluding 'date')
     * @return array           [['date' => 'Y-m-d', 'count' => int], ...]
     *
     * @throws \RuntimeException  When the server CSV is missing or unreadable
     */
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

    /**
     * Read an uploaded CSV file (stored under storage/app/private/lstm/uploads/)
     * and return a time series for the given metric.
     *
     * @param  string $storagePath  Path returned by Storage::putFile() / UploadedFile::store()
     * @param  string $metric
     * @return array
     *
     * @throws \RuntimeException|\InvalidArgumentException
     */
    public function readUploadedCsv(string $storagePath, string $metric = 'visitors'): array
    {
        $path = Storage::disk(self::DISK)->path($storagePath);

        if (!file_exists($path)) {
            throw new \RuntimeException('Uploaded CSV not found: ' . $storagePath);
        }

        return $this->parseCsv($path, $metric);
    }

    /**
     * Read the bundled server CSV and return a time series where 'count' is the
     * sum of two or more metric columns — parsed in a **single** file pass.
     *
     * This avoids opening and scanning the same CSV file multiple times when
     * the caller needs to add several columns together (e.g. offline_room_bookings
     * + online_room_bookings to get a combined room-booking count).
     *
     * @param  string[] $metrics  Two or more column names from REQUIRED_COLUMNS (not 'date')
     * @return array              [['date' => 'Y-m-d', 'count' => int], ...]
     *
     * @throws \RuntimeException|\InvalidArgumentException
     */
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

    /**
     * Read an uploaded CSV file and return a time series where 'count' is the
     * sum of two or more metric columns — parsed in a **single** file pass.
     *
     * @param  string   $storagePath
     * @param  string[] $metrics
     * @return array
     *
     * @throws \RuntimeException|\InvalidArgumentException
     */
    public function readUploadedCsvColumnsSummed(string $storagePath, array $metrics): array
    {
        $path = Storage::disk(self::DISK)->path($storagePath);

        if (!file_exists($path)) {
            throw new \RuntimeException('Uploaded CSV not found: ' . $storagePath);
        }

        return $this->parseCsvMultiColumn($path, $metrics);
    }

    /**
     * Validate that an uploaded file has the required columns.
     * Returns an array of missing column names (empty = valid).
     *
     * @param  string $storagePath
     * @return string[]
     */
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

    /**
     * Return metadata about the server CSV (row count, date range).
     */
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

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Parse a CSV file into a [{date, count}] array.
     *
     * @throws \InvalidArgumentException  When required columns are missing
     */
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

        // Read header row
        $rawHeaders = fgetcsv($handle);
        if ($rawHeaders === false) {
            fclose($handle);
            throw new \RuntimeException("CSV is empty: {$absolutePath}");
        }

        $headers = array_map('trim', array_map('strtolower', $rawHeaders));

        // Check required columns
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
                continue; // skip malformed rows
            }

            $date  = trim($row[$dateIdx]);
            $count = trim($row[$metricIdx]);

            // Basic date sanity check
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $timeSeries[] = [
                'date'  => $date,
                'count' => max(0, (int) $count),
            ];
        }

        fclose($handle);

        // Sort ascending by date (CSV should already be sorted, but be safe)
        usort($timeSeries, fn($a, $b) => strcmp($a['date'], $b['date']));

        Log::info('CsvDataReader: parsed CSV', [
            'path'   => basename($absolutePath),
            'metric' => $metric,
            'rows'   => count($timeSeries),
        ]);

        return $timeSeries;
    }

    /**
     * Parse a CSV file into a [{date, count}] array where 'count' is the sum
     * of all requested $metrics columns — in a single file pass.
     *
     * Identical validation and row-filtering behaviour as parseCsv().
     *
     * @param  string   $absolutePath
     * @param  string[] $metrics  Must be valid non-date REQUIRED_COLUMNS entries
     * @return array
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    private function parseCsvMultiColumn(string $absolutePath, array $metrics): array
    {
        // Validate every requested metric up front
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

        // Read header row
        $rawHeaders = fgetcsv($handle);
        if ($rawHeaders === false) {
            fclose($handle);
            throw new \RuntimeException("CSV is empty: {$absolutePath}");
        }

        $headers = array_map('trim', array_map('strtolower', $rawHeaders));

        // Check required columns
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

        // Resolve column index for each requested metric once, before the loop
        $metricIdxs = [];
        foreach ($metrics as $m) {
            $metricIdxs[] = array_search($m, $headers, true);
        }
        $maxIdx = max($dateIdx, ...$metricIdxs);

        $timeSeries = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) <= $maxIdx) {
                continue; // skip malformed rows
            }

            $date = trim($row[$dateIdx]);

            // Basic date sanity check
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            // Sum all requested metric columns for this row in one inner pass
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

        // Sort ascending by date (CSV should already be sorted, but be safe)
        usort($timeSeries, fn($a, $b) => strcmp($a['date'], $b['date']));

        Log::info('CsvDataReader: parsed CSV (multi-column)', [
            'path'    => basename($absolutePath),
            'metrics' => implode('+', $metrics),
            'rows'    => count($timeSeries),
        ]);

        return $timeSeries;
    }
}
