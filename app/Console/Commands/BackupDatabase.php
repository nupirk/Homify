<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Dumps the database to storage/backups and prunes old archives.
 *
 * Restore (pg_dump -Fc format):
 *   pg_restore --clean --if-exists --no-owner \
 *     -h <DB_HOST> -p <DB_PORT> -U <DB_USERNAME> -d <DB_DATABASE> \
 *     storage/backups/<file>.dump
 *
 * The dump format is `-Fc` (custom) — it compresses and lets pg_restore
 * recreate objects selectively. Set PG_DUMP_BINARY when pg_dump is not on
 * PATH (common on Windows: "C:\Program Files\PostgreSQL\18\bin\pg_dump.exe").
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--path= : Directory to write the archive to (defaults to storage/backups)}
        {--keep= : Days of archives to retain (defaults to config app.backup_retention_days)}';

    protected $description = 'Create a compressed database backup and prune old ones';

    public function handle(): int
    {
        $connection = config('database.default');

        if ($connection !== 'pgsql') {
            $this->error("db:backup only supports the pgsql driver (current: {$connection}).");
            return self::FAILURE;
        }

        $directory = $this->option('path') ?: storage_path('backups');
        File::ensureDirectoryExists($directory);

        $binary = config('app.pg_dump_binary', 'pg_dump');
        $config = config("database.connections.{$connection}");
        $file = $directory . DIRECTORY_SEPARATOR . 'urban_' . now()->format('Ymd_His') . '.dump';

        $process = new Process([
            $binary,
            '-Fc',
            '--no-owner',
            '-h', (string) $config['host'],
            '-p', (string) $config['port'],
            '-U', (string) $config['username'],
            '-f', $file,
            (string) $config['database'],
        ], null, [
            'PGPASSWORD' => (string) $config['password'],
        ]);

        $process->setTimeout(600);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            // Covers both a missing binary and a failed dump; the archive may
            // be partially written, so never leave a corrupt file behind.
            if (File::exists($file)) {
                File::delete($file);
            }

            $this->error('Backup failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Backup written: ' . $file);

        $keepDays = (int) ($this->option('keep') ?: config('app.backup_retention_days', 14));
        $this->prune($directory, $keepDays);

        return self::SUCCESS;
    }

    private function prune(string $directory, int $keepDays): void
    {
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() !== 'dump') {
                continue;
            }
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->info("Pruned {$removed} archive(s) older than {$keepDays} day(s).");
        }
    }
}
