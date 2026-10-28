<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Database backups: a compressed dump in storage/app/backups, old ones removed, optionally copied to another folder. */
class Backup
{
    public function directory(): string
    {
        return storage_path('app/backups');
    }

    /** @param  array<string, mixed>  $db one entry of config('database.connections') */
    public function dumpCommand(array $db): array
    {
        return ['mysqldump', '--single-transaction', '--no-tablespaces', '--routines',
            '-h', (string) ($db['host'] ?? '127.0.0.1'), '-P', (string) ($db['port'] ?? 3306),
            '-u', (string) ($db['username'] ?? 'root'), (string) ($db['database'] ?? '')];
    }

    /** @return string path of the new backup file */
    public function run(): string
    {
        File::ensureDirectoryExists($this->directory());
        $name = 'aichat-'.now()->format('Ymd-His');
        $db = (array) config('database.connections.'.config('database.default'));

        if (($db['driver'] ?? '') === 'sqlite') {
            $source = (string) $db['database'];
            if ($source === ':memory:' || ! is_file($source)) {
                throw new RuntimeException('There is no database file to copy.');
            }
            $target = $this->directory().'/'.$name.'.sqlite';
            File::copy($source, $target);

            return $target;
        }

        $target = $this->directory().'/'.$name.'.sql.gz';
        $process = new Process($this->dumpCommand($db), null, ['MYSQL_PWD' => (string) ($db['password'] ?? '')], null, 600);
        $handle = gzopen($target, 'wb9');
        $process->run(function ($type, $buffer) use ($handle): void {
            if ($type === Process::OUT) {
                gzwrite($handle, $buffer);
            }
        });
        gzclose($handle);

        if (! $process->isSuccessful()) {
            File::delete($target);
            throw new RuntimeException('mysqldump failed: '.trim(mb_substr($process->getErrorOutput(), 0, 300)));
        }

        return $target;
    }

    /** Keep only the newest $keep backups. @return int how many were removed */
    public function prune(int $keep): int
    {
        $files = collect(File::glob($this->directory().'/aichat-*'))->sortDesc()->values();
        $old = $files->slice(max(1, $keep));
        $old->each(fn ($f) => File::delete($f));

        return $old->count();
    }
}
