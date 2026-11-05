<?php

namespace App\Services\Backups;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Database backups the super admin makes, downloads and deletes by hand: a compressed SQL file
 * (pg_dump) in the backups folder. Nothing runs on a schedule and nothing is deleted
 * automatically. The folder is a Railway volume on the web service, so files survive deploys.
 *
 * Customers' names, emails and order details are encrypted in the database, so they stay
 * encrypted inside every backup file too.
 */
class DatabaseBackup
{
    public function path(): string
    {
        return rtrim((string) config('backups.path'), '/');
    }

    /** Whether the folder is its own mounted disk (a Railway volume), so files outlive deploys. */
    public function persistent(): bool
    {
        $mounts = @file_get_contents('/proc/mounts');

        return is_string($mounts) && collect(explode("\n", $mounts))->contains(fn ($line) => (explode(' ', $line)[1] ?? null) === $this->path());
    }

    /**
     * The backup being made right now (its .part file, less than 30 minutes old), or null. A
     * .part older than that is a backup that died and is cleaned up.
     */
    public function running(): ?array
    {
        foreach (is_dir($this->path()) ? glob($this->path().'/growvia-*.sql.gz.part') : [] as $part) {
            if (filemtime($part) < time() - 1800) {
                @unlink($part);

                continue;
            }

            return ['name' => basename($part, '.part'), 'size' => (int) filesize($part), 'started' => Carbon::createFromTimestamp(filectime($part))];
        }

        return null;
    }

    /** Names the next backup and marks it running (an empty .part file), before it starts. */
    public function reserve(): string
    {
        File::ensureDirectoryExists($this->path(), 0700);
        $name = 'growvia-'.now()->utc()->format('Y-m-d-His').'.sql.gz';
        touch($this->path().'/'.$name.'.part');

        return $name;
    }

    /** The last backup's error, if it failed. */
    public function lastError(): ?string
    {
        return \Illuminate\Support\Facades\Cache::get('backup:last_error');
    }

    /**
     * Makes a backup now (run after the response by the admin page, so the page never waits).
     * pg_dump only reads: the app keeps working while it runs. Returns its file name.
     */
    public function create(?string $name = null): string
    {
        \Illuminate\Support\Facades\Cache::forget('backup:last_error');
        $name ??= $this->reserve();
        $part = $this->path().'/'.$name.'.part';
        $db = config('database.connections.'.config('database.default'));
        if (($db['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('Backups need the PostgreSQL database.');
        }

        // Plain SQL, compressed; no owners or grants so it restores into any database.
        $result = Process::timeout(1800)->env(['PGPASSWORD' => (string) $db['password']])->run([
            'pg_dump', '--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--dbname='.$db['database'],
            '--no-owner', '--no-privileges', '--format=plain', '--compress=6', '--file='.$part,
        ]);
        if (! $result->successful()) {
            @unlink($part);
            $error = 'pg_dump failed: '.trim(mb_substr($result->errorOutput(), 0, 500));
            \Illuminate\Support\Facades\Cache::put('backup:last_error', $error, 86400);
            throw new RuntimeException($error);
        }
        rename($part, $this->path().'/'.$name);
        @chmod($this->path().'/'.$name, 0600);

        return $name;
    }

    /** @return list<array{name: string, size: int, at: Carbon}> newest first */
    public function list(): array
    {
        if (! is_dir($this->path())) {
            return [];
        }

        return collect(File::files($this->path()))
            ->filter(fn ($f) => preg_match('/^growvia-[\d-]+\.sql\.gz$/', $f->getFilename()))
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'at' => Carbon::createFromTimestamp($f->getMTime())])
            ->sortByDesc('name')->values()->all();
    }

    /** The full path of a backup by name, or null (names are checked, so no other file can be read). */
    public function file(string $name): ?string
    {
        if (! preg_match('/^growvia-[\d-]+\.sql\.gz$/', $name)) {
            return null;
        }
        $file = $this->path().'/'.$name;

        return is_file($file) ? $file : null;
    }

    public function delete(string $name): bool
    {
        $file = $this->file($name);

        return $file !== null && @unlink($file);
    }

    /** Free space on the backups disk, in bytes (null when unknown). */
    public function freeSpace(): ?int
    {
        $free = @disk_free_space(is_dir($this->path()) ? $this->path() : dirname($this->path()));

        return $free === false ? null : (int) $free;
    }

    /** "12.4 MB" (no intl extension needed). */
    public static function size(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i ? number_format($bytes, 1) : (string) (int) $bytes).' '.$units[$i];
    }
}
