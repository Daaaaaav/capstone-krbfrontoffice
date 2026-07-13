<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupDatabase extends Command
{
    /**
     * Usage:
     *   php artisan db:backup                    → full dump
     *   php artisan db:backup --tables=deliveries,storages,guestbooks
     */
    protected $signature = 'db:backup
                            {--tables= : Comma-separated list of tables to back up (default: all)}
                            {--dir=    : Output directory (default: storage/app/backups)}';

    protected $description = 'Dump the MySQL database (or specific tables) to a timestamped SQL file';

    public function handle(): int
    {
        $host     = config('database.connections.mysql.host', '127.0.0.1');
        $port     = config('database.connections.mysql.port', 3306);
        $db       = config('database.connections.mysql.database');
        $user     = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        if (!$db || !$user) {
            $this->error('Database credentials not configured.');
            return self::FAILURE;
        }

        $dir = $this->option('dir')
            ?: storage_path('app/backups');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tables     = $this->option('tables');
        $tableList  = $tables ? array_map('trim', explode(',', $tables)) : [];
        $tableSuffix = $tableList ? '_' . implode('-', $tableList) : '_full';
        $filename   = $dir . '/backup_' . now()->format('Ymd_His') . $tableSuffix . '.sql';

        $tableArgs = implode(' ', $tableList);

        // Build the mysqldump command
        $passArg = $password ? "-p" . escapeshellarg($password) : '';
        $cmd = sprintf(
            'mysqldump -h %s -P %s -u %s %s --single-transaction --skip-lock-tables %s > %s',
            escapeshellarg($host),
            (int) $port,
            escapeshellarg($user),
            $passArg,
            escapeshellarg($db) . ($tableArgs ? ' ' . $tableArgs : ''),
            escapeshellarg($filename)
        );

        $this->info("Backing up" . ($tableList ? ' tables: ' . implode(', ', $tableList) : ' full database') . '...');

        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('mysqldump failed: ' . implode("\n", $output));
            return self::FAILURE;
        }

        $size = round(filesize($filename) / 1024, 1);
        $this->info("✅ Backup saved: {$filename} ({$size} KB)");

        return self::SUCCESS;
    }
}
