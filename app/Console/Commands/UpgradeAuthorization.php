<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class UpgradeAuthorization extends Command
{
    protected $signature = 'maintenance:upgrade-access';

    protected $description = 'Cadangkan database lalu terapkan migrasi hak akses dalam maintenance mode';

    public function handle(): int
    {
        if (DB::table('migrations')->where('migration', '2026_11_14_000002_use_personal_modules')->exists()) {
            $this->info('Migrasi hak akses sudah diterapkan.');

            return self::SUCCESS;
        }
        $wasDown = app()->isDownForMaintenance();
        $migrated = false;
        try {
            if (! $wasDown) {
                Artisan::call('down');
            }
            $connection = config('database.connections.'.config('database.default'));
            $directory = storage_path('app/backups');
            File::ensureDirectoryExists($directory);
            $path = $directory.'/before-authorization-'.now()->format('Ymd-His');
            if ($connection['driver'] === 'mysql') {
                $path .= '.sql';
                $binary = PHP_OS_FAMILY === 'Windows' && is_file('C:/xampp/mysql/bin/mysqldump.exe') ? 'C:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump';
                $process = new Process([$binary, '--host='.$connection['host'], '--port='.$connection['port'], '--user='.$connection['username'], '--single-transaction', '--routines', '--triggers', '--result-file='.$path, $connection['database']], null, ['MYSQL_PWD' => $connection['password']]);
                $process->setTimeout(300);
                $process->mustRun();
            } elseif ($connection['driver'] === 'sqlite' && $connection['database'] !== ':memory:') {
                $path .= '.sqlite';
                DB::statement('PRAGMA wal_checkpoint(FULL)');
                File::copy($connection['database'], $path);
            } else {
                throw new \RuntimeException('Driver backup tidak didukung.');
            }
            if (! is_file($path) || filesize($path) === 0) {
                throw new \RuntimeException('Backup kosong; migrasi dibatalkan.');
            }
            $this->info('Backup: '.$path);
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new \RuntimeException('Migrasi gagal. Periksa log dan pulihkan backup sebelum membuka aplikasi.');
            }
            $migrated = true;
            Artisan::call('view:clear');
            $this->info('Hak akses berhasil diaktifkan.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Upgrade gagal: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if (! $wasDown && $migrated) {
                Artisan::call('up');
            }
        }
    }
}
