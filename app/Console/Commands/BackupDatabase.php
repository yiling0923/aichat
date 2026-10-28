<?php

namespace App\Console\Commands;

use App\Support\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'aichat:backup {--keep=14 : how many backups to keep} {--copy-to= : also copy the file to this folder (another disk or a synced folder)}';

    protected $description = 'Save a compressed copy of the database (daily on the server)';

    public function handle(Backup $backup): int
    {
        try {
            $file = $backup->run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($copy = $this->option('copy-to')) {
            File::ensureDirectoryExists($copy);
            File::copy($file, rtrim($copy, '/\\').DIRECTORY_SEPARATOR.basename($file));
        }

        $removed = $backup->prune((int) $this->option('keep'));
        $this->info('Backup saved: '.$file.($removed ? " ({$removed} old removed)" : ''));

        return self::SUCCESS;
    }
}
