<?php

namespace App\Services\Backups;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Database backups the super admin makes, downloads and deletes by hand: a compressed SQL file
 * (pg_dump) in a private Railway bucket, so it survives deploys. Delete removes it from the
 * bucket. Nothing runs on a schedule and nothing is deleted automatically.
 *
 * pg_dump only reads, so the app keeps working while it runs. Customers' names, emails and order
 * details are encrypted in the database, so they stay encrypted inside every backup file too.
 */
class DatabaseBackup
{
    private const PATTERN = '/^growvia-[\d-]+\.sql\.gz$/';

    /** A backup still "running" after this long died (server restart) and is cleared. */
    private const STALE_SECONDS = 1800;

    public function disk(): Filesystem
    {
        return Storage::disk('backups');
    }

    /** Whether the bucket is connected (Railway added its variables to this service). */
    public function configured(): bool
    {
        $c = config('filesystems.disks.backups');

        return ($c['driver'] ?? null) !== 's3' || (! empty($c['bucket']) && ! empty($c['key']) && ! empty($c['secret']));
    }

    /** @return list<array{name: string, size: int, at: Carbon}> newest first */
    public function list(): array
    {
        if (! $this->configured()) {
            return [];
        }

        return collect($this->disk()->files())
            ->filter(fn ($f) => preg_match(self::PATTERN, $f))
            ->map(fn ($f) => ['name' => $f, 'size' => (int) $this->disk()->size($f), 'at' => Carbon::createFromTimestamp($this->disk()->lastModified($f))])
            ->sortByDesc('name')->values()->all();
    }

    /** The backup being made now: [name, started], or null. */
    public function running(): ?array
    {
        $r = Cache::get('backup:running');
        if ($r && $r['started'] < time() - self::STALE_SECONDS) {
            Cache::forget('backup:running');

            return null;
        }

        return $r ? ['name' => $r['name'], 'started' => Carbon::createFromTimestamp($r['started'])] : null;
    }

    public function lastError(): ?string
    {
        return Cache::get('backup:last_error');
    }

    /** Names the next backup and marks it running, before it starts. */
    public function reserve(): string
    {
        $name = 'growvia-'.now()->utc()->format('Y-m-d-His').'.sql.gz';
        Cache::put('backup:running', ['name' => $name, 'started' => time()], self::STALE_SECONDS);
        Cache::forget('backup:last_error');

        return $name;
    }

    /** pg_dump into a temporary file, uploaded to the bucket, then the temporary file is removed. */
    public function create(string $name): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'growvia-backup-');
        try {
            $db = config('database.connections.'.config('database.default'));
            if (($db['driver'] ?? null) !== 'pgsql') {
                throw new RuntimeException('Backups need the PostgreSQL database.');
            }
            // Plain SQL, compressed; no owners or grants so it restores into any database.
            // Production connects with a URL (DB_URL); host/port/user then aren't set separately.
            // pg_dump takes the URL as --dbname, password included, so it's never on the command line twice.
            $connection = ! empty($db['url'])
                ? ['--dbname='.$db['url']]
                : ['--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--dbname='.$db['database']];
            $result = Process::timeout(1800)->env(empty($db['url']) ? ['PGPASSWORD' => (string) $db['password']] : [])->run([
                'pg_dump', ...$connection, '--no-owner', '--no-privileges', '--format=plain', '--compress=6', '--file='.$tmp,
            ]);
            if (! $result->successful()) {
                throw new RuntimeException('pg_dump failed: '.trim(mb_substr($result->errorOutput(), 0, 500)));
            }
            $this->upload($name, $tmp);
        } catch (Throwable $e) {
            Cache::put('backup:last_error', 'The last backup failed: '.mb_substr($e->getMessage(), 0, 500), 86400);
            throw $e;
        } finally {
            @unlink($tmp);
            Cache::forget('backup:running');
        }
    }

    /** Streams a local file into the bucket (never the whole file in memory). */
    public function upload(string $name, string $file): void
    {
        $in = fopen($file, 'rb');
        try {
            $this->disk()->writeStream($name, $in, ['visibility' => 'private', 'ContentType' => 'application/gzip']);
        } finally {
            if (is_resource($in)) {
                fclose($in);
            }
        }
    }

    /** Whether a backup with this name exists (names are checked, so no other file can be reached). */
    public function exists(string $name): bool
    {
        return preg_match(self::PATTERN, $name) === 1 && $this->configured() && $this->disk()->exists($name);
    }

    /** @return resource */
    public function read(string $name)
    {
        return $this->disk()->readStream($name);
    }

    public function delete(string $name): bool
    {
        return $this->exists($name) && $this->disk()->delete($name);
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
