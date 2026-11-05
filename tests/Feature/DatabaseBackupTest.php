<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Backups\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Super admin › Database backups in the bucket: made, downloaded (logged) and deleted by hand. */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    /** A connected bucket (Railway's variables), faked locally. */
    private function bucket(): void
    {
        config(['filesystems.disks.backups.bucket' => 'growvia-backups', 'filesystems.disks.backups.key' => 'k', 'filesystems.disks.backups.secret' => 's']);
        Storage::fake('backups');
    }

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Ava', 'email' => 'owner@growvia.test', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => 'super_admin']);
    }

    public function test_backups_in_the_bucket_are_listed_downloaded_and_deleted(): void
    {
        $this->bucket();
        $backups = app(DatabaseBackup::class);
        $file = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($file, $content = gzencode('-- dump'));
        $backups->upload('growvia-2026-11-05-101500.sql.gz', $file);
        unlink($file);
        Storage::disk('backups')->put('other.txt', 'not a backup');

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/backups')->assertOk()->assertSee('growvia-2026-11-05-101500.sql.gz')->assertDontSee('other.txt');
        $download = $this->actingAs($admin)->get('/admin/backups/growvia-2026-11-05-101500.sql.gz')->assertOk()->assertDownload('growvia-2026-11-05-101500.sql.gz');
        $this->assertSame($content, $download->streamedContent());
        $this->assertSame('database backup download', AuditLog::where('action', 'personal_data.viewed')->sole()->context['what']);
        $this->actingAs($admin)->get('/admin/backups/other.txt')->assertNotFound();

        // Delete removes it from the bucket.
        $this->actingAs($admin)->post('/admin/backups/growvia-2026-11-05-101500.sql.gz/delete')->assertRedirect();
        Storage::disk('backups')->assertMissing('growvia-2026-11-05-101500.sql.gz');
        $this->assertSame([], $backups->list());
    }

    public function test_a_running_backup_shows_without_buttons_and_blocks_another(): void
    {
        $this->bucket();
        $name = app(DatabaseBackup::class)->reserve();
        $admin = $this->admin();
        $page = $this->actingAs($admin)->get('/admin/backups')->assertOk()->assertSee('Backing up…')->assertSee('http-equiv="refresh"', false);
        $page->assertDontSee(route('admin.backups.download', $name));
        $this->actingAs($admin)->post('/admin/backups')->assertSessionHasErrors('backup');
    }

    public function test_without_the_bucket_the_page_says_how_to_connect_it(): void
    {
        config(['filesystems.disks.backups.bucket' => null, 'filesystems.disks.backups.key' => null]);
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/backups')->assertOk()->assertSee('bucket isn')->assertSee('connected yet');
        $this->actingAs($admin)->post('/admin/backups')->assertSessionHasErrors('backup');
    }

    public function test_pg_dump_connects_with_the_database_url(): void
    {
        $this->bucket();
        \Illuminate\Support\Facades\Process::fake();
        $backups = app(DatabaseBackup::class);
        $name = $backups->reserve();
        // Postgres only for the backup call (the test's own database stays SQLite).
        $default = config('database.default');
        config(['database.default' => 'pgsql', 'database.connections.pgsql.url' => 'postgresql://u:p@db.internal:5432/railway']);
        try {
            $backups->create($name);
        } finally {
            config(['database.default' => $default]);
        }
        \Illuminate\Support\Facades\Process::assertRan(fn ($p) => in_array('--dbname=postgresql://u:p@db.internal:5432/railway', (array) $p->command, true) && ! in_array('--host=127.0.0.1', (array) $p->command, true));
        $this->assertNotNull($backups->list()[0] ?? null, 'Uploaded to the bucket.');
        $this->assertNull($backups->running());
    }
}
