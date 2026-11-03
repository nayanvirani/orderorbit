<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpsAlert;
use App\Support\TeamAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Ops alerts (G26): what's broken now, what was, and whether the checks are running. */
class OpsController extends Controller
{
    public function index(): View
    {
        return view('admin.ops', [
            'open' => OpsAlert::whereNull('resolved_at')->latest('last_seen_at')->get(),
            'recent' => OpsAlert::whereNotNull('resolved_at')->where('resolved_at', '>=', now()->subDays(30))->latest('resolved_at')->limit(30)->get(),
            'lastRun' => ($r = Cache::get('ops-monitor:last-run')) ? \Illuminate\Support\Carbon::parse($r) : null,
            'recipient' => TeamAlert::recipient(),
        ]);
    }

    public function resolve(Request $request, int $alert): RedirectResponse
    {
        OpsAlert::whereKey($alert)->whereNull('resolved_at')->update(['resolved_at' => now()]);

        return back()->with('status', 'Alert closed. It opens again if the problem comes back.');
    }
}
