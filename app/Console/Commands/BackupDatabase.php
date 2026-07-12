<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Backup MySQL database';

    public function handle(): void
    {
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup-'.now()->format('Y-m-d-H-i-s').'.sql';
        $path = $backupDir.DIRECTORY_SEPARATOR.$filename;

        $db = config('database.connections.mysql');
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s > "%s"',
            escapeshellarg($db['username']),
            escapeshellarg($db['password']),
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['database']),
            $path
        );

        $exitCode = 0;
        $output = null;

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Database backup failed');

            return;
        }

        $this->info("Database backed up to {$path}");
    }
}
