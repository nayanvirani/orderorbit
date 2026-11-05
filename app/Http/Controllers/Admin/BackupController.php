<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Backups\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Super admin › Database backups: make one, download it (logged) and delete it, all by hand. */
class BackupController extends Controller
{
    public function __construct(private readonly DatabaseBackup $backups) {}

    public function index(): View
    {
        return view('admin.backups', ['backups' => $this->backups->list(), 'running' => $this->backups->running(), 'error' => $this->backups->lastError(),
            'persistent' => $this->backups->persistent(), 'free' => $this->backups->freeSpace()]);
    }

    /** Starts a backup after the response is sent; the page shows it as running until it's ready. */
    public function create(Request $request): RedirectResponse
    {
        if ($this->backups->running()) {
            return back()->withErrors(['backup' => 'A backup is already running. It appears below when it\'s ready.']);
        }
        if (config('database.connections.'.config('database.default').'.driver') !== 'pgsql') {
            return back()->withErrors(['backup' => 'Backups need the PostgreSQL database.']);
        }
        $by = $request->user()->email;
        // Marked running before the response, so the page shows it at once.
        $name = $this->backups->reserve();
        defer(function () use ($by, $name) {
            set_time_limit(1800);
            try {
                $this->backups->create($name);
                AuditLog::record('admin.backup_created', null, ['file' => $name, 'by' => $by]);
            } catch (Throwable $e) {
                report($e);
            }
        });

        return redirect()->route('admin.backups')->with('status', 'Backup started. It appears below when it\'s ready; this page refreshes by itself.');
    }

    public function download(Request $request, string $name): BinaryFileResponse
    {
        $file = $this->backups->file($name) ?? abort(404);
        // A backup holds every store's data: each download is on the record.
        AuditLog::viewed('database backup download', null, ['file' => $name]);

        return response()->download($file, $name, ['Content-Type' => 'application/gzip', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, string $name): RedirectResponse
    {
        abort_unless($this->backups->delete($name), 404);
        AuditLog::record('admin.backup_deleted', null, ['file' => $name, 'by' => $request->user()->email]);

        return back()->with('status', "Deleted {$name}.");
    }
}
