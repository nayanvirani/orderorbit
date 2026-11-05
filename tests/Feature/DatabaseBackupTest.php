<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Backups\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Super admin › Database backups: listed, downloaded (logged) and deleted by hand. */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backups_are_listed_downloaded_and_deleted(): void
    {
        $dir = storage_path('framework/testing/backups-'.uniqid());
        config(['backups.path' => $dir]);
        File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/growvia-2026-11-05-101500.sql.gz", gzencode('-- dump'));
        file_put_contents("{$dir}/secret.txt", 'not a backup');

        $admin = User::forceCreate(['name' => 'Ava', 'email' => 'owner@growvia.test', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => 'super_admin']);
        $this->actingAs($admin)->get('/admin/backups')->assertOk()->assertSee('growvia-2026-11-05-101500.sql.gz')->assertDontSee('secret.txt');
        $this->actingAs($admin)->get('/admin/backups/growvia-2026-11-05-101500.sql.gz')->assertOk()->assertDownload('growvia-2026-11-05-101500.sql.gz');
        $this->assertSame('database backup download', AuditLog::where('action', 'personal_data.viewed')->sole()->context['what']);
        $this->actingAs($admin)->get('/admin/backups/secret.txt')->assertNotFound();

        $this->actingAs($admin)->post('/admin/backups/growvia-2026-11-05-101500.sql.gz/delete')->assertRedirect();
        $this->assertFileDoesNotExist("{$dir}/growvia-2026-11-05-101500.sql.gz");
        $this->assertSame([], app(DatabaseBackup::class)->list());
        File::deleteDirectory($dir);
    }

    public function test_only_postgres_can_be_backed_up(): void
    {
        $admin = User::forceCreate(['name' => 'Ava', 'email' => 'owner@growvia.test', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => 'super_admin']);
        $this->actingAs($admin)->post('/admin/backups')->assertRedirect()->assertSessionHasErrors('backup');
    }

    public function test_a_running_backup_shows_without_buttons_and_blocks_another(): void
    {
        $dir = storage_path('framework/testing/backups-'.uniqid());
        config(['backups.path' => $dir]);
        $backups = app(DatabaseBackup::class);
        $name = $backups->reserve();
        $admin = User::forceCreate(['name' => 'Ava', 'email' => 'owner@growvia.test', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => 'super_admin']);

        $page = $this->actingAs($admin)->get('/admin/backups')->assertOk()->assertSee('Backing up…')->assertSee('http-equiv="refresh"', false);
        $page->assertDontSee(route('admin.backups.download', $name));
        $this->actingAs($admin)->post('/admin/backups')->assertSessionHasErrors('backup');
        File::deleteDirectory($dir);
    }
}
