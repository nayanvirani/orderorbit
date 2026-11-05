<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Backups\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Super admin › Database backups: make one, download it (logged) and delete it, all by hand. */
class BackupController extends Controller
{
    public function __construct(private readonly DatabaseBackup $backups) {}

    public function index(): View
    {
        $configured = $this->backups->configured();
        $error = null;
        try {
            $backups = $this->backups->list();
        } catch (Throwable $e) {
            report($e);
            $backups = [];
            $error = 'Couldn\'t read the backups bucket: '.mb_substr($e->getMessage(), 0, 200);
        }

        return view('admin.backups', ['backups' => $backups, 'running' => $this->backups->running(), 'configured' => $configured,
            'error' => $error ?? $this->backups->lastError()]);
    }

    /** Starts a backup after the response is sent; the page shows it as running until it's in the bucket. */
    public function create(Request $request): RedirectResponse
    {
        if (! $this->backups->configured()) {
            return back()->withErrors(['backup' => 'Connect the backups bucket to this service in Railway first.']);
        }
        if ($this->backups->running()) {
            return back()->withErrors(['backup' => 'A backup is already running. Download and Delete appear when it\'s ready.']);
        }
        if (config('database.connections.'.config('database.default').'.driver') !== 'pgsql') {
            return back()->withErrors(['backup' => 'Backups need the PostgreSQL database.']);
        }
        $by = $request->user()->email;
        // Marked running before the response, so the page shows it at once.
        $name = $this->backups->reserve();
        defer(function () use ($name, $by) {
            set_time_limit(1800);
            try {
                $this->backups->create($name);
                AuditLog::record('admin.backup_created', null, ['file' => $name, 'by' => $by]);
            } catch (Throwable $e) {
                report($e);
            }
        });

        return redirect()->route('admin.backups')->with('status', 'Backup started. This page refreshes by itself until it\'s ready.');
    }

    public function download(string $name): StreamedResponse
    {
        abort_unless($this->backups->exists($name), 404);
        // A backup holds every store's data: each download is on the record.
        AuditLog::viewed('database backup download', null, ['file' => $name]);

        return response()->streamDownload(function () use ($name) {
            $in = $this->backups->read($name);
            fpassthru($in);
            fclose($in);
        }, $name, ['Content-Type' => 'application/gzip', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, string $name): RedirectResponse
    {
        abort_unless($this->backups->delete($name), 404);
        AuditLog::record('admin.backup_deleted', null, ['file' => $name, 'by' => $request->user()->email]);

        return back()->with('status', "Deleted {$name} from the bucket.");
    }
}
