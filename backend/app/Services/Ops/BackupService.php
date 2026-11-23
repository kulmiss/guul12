<?php

namespace App\Services\Ops;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Daily database backup: a gzipped SQL dump (MySQL/MariaDB) or SQLite copy in storage/app/backups.
 * Backups hold student data: they stay on the server (never in git) and only the newest are kept.
 */
class BackupService
{
    public function __construct(
        private readonly string $dir,
        private readonly int $keep,
        private readonly string $mysqldump,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('guul.backups.path'),
            max(1, (int) config('guul.backups.keep')),
            (string) config('guul.backups.mysqldump'),
        );
    }

    /**
     * Makes a backup, checks it, and removes the oldest beyond the limit.
     *
     * @return string path of the new backup
     *
     * @throws BackupFailed
     */
    public function run(): string
    {
        if (! is_dir($this->dir) && ! @mkdir($this->dir, 0700, true) && ! is_dir($this->dir)) {
            throw new BackupFailed('Cannot create the backup folder.');
        }
        $name = 'guul12-'.Carbon::now()->utc()->format('Ymd-His');
        $driver = DB::connection()->getDriverName();
        $path = match ($driver) {
            'mysql', 'mariadb' => $this->mysql($this->dir.'/'.$name.'.sql.gz'),
            'sqlite' => $this->sqlite($this->dir.'/'.$name.'.sqlite.gz'),
            default => throw new BackupFailed("No backup method for the {$driver} database."),
        };
        $this->verify($path, $driver);
        $this->rotate();

        return $path;
    }

    /** Newest backup time, or null when there is none. */
    public function lastBackupAt(): ?Carbon
    {
        $files = $this->files();

        return $files === [] ? null : Carbon::createFromTimestamp((int) filemtime($files[0]));
    }

    /**
     * Backup files, newest first.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $files = glob($this->dir.'/guul12-*.gz') ?: [];
        // The name holds the UTC time, so sorting by name is sorting by age.
        rsort($files, SORT_STRING);

        return $files;
    }

    private function mysql(string $path): string
    {
        $c = DB::connection()->getConfig();
        $process = new Process(
            [
                $this->mysqldump,
                '--single-transaction', '--quick', '--no-tablespaces',
                '--host='.(string) ($c['host'] ?? '127.0.0.1'),
                '--port='.(string) ($c['port'] ?? 3306),
                '--user='.(string) ($c['username'] ?? ''),
                (string) ($c['database'] ?? ''),
            ],
            null,
            // The password goes through the environment, never on the command line.
            ['MYSQL_PWD' => (string) ($c['password'] ?? '')],
            null,
            1800,
        );
        $out = gzopen($path, 'wb6');
        if ($out === false) {
            throw new BackupFailed('Cannot write the backup file.');
        }
        $process->run(function (string $type, string $data) use ($out): void {
            if ($type === Process::OUT) {
                gzwrite($out, $data);
            }
        });
        gzclose($out);
        if (! $process->isSuccessful()) {
            @unlink($path);
            // stderr may name the user and host, never the password.
            throw new BackupFailed('mysqldump failed: '.trim($process->getErrorOutput()));
        }
        chmod($path, 0600);

        return $path;
    }

    private function sqlite(string $path): string
    {
        $tmp = $path.'.tmp';
        @unlink($tmp);
        // VACUUM INTO makes a consistent copy, also of an in-memory database.
        DB::statement('VACUUM INTO ?', [$tmp]);
        $in = fopen($tmp, 'rb');
        $out = gzopen($path, 'wb6');
        if ($in === false || $out === false) {
            throw new BackupFailed('Cannot write the backup file.');
        }
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        unlink($tmp);
        chmod($path, 0600);

        return $path;
    }

    private function verify(string $path, string $driver): void
    {
        $gz = gzopen($path, 'rb');
        $head = $gz === false ? '' : (string) gzread($gz, 64);
        if ($gz !== false) {
            gzclose($gz);
        }
        $ok = $driver === 'sqlite'
            ? str_starts_with($head, "SQLite format 3\0")
            : str_contains($head, 'dump');
        if (! $ok) {
            @unlink($path);
            throw new BackupFailed('The new backup looks broken and was removed.');
        }
    }

    private function rotate(): void
    {
        foreach (array_slice($this->files(), $this->keep) as $old) {
            unlink($old);
        }
    }
}
