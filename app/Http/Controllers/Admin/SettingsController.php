<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform settings: billing rules and test stores, stored in the database over the defaults.
 */
class SettingsController extends Controller
{
    public function show(): View
    {
        return view('admin.settings', [
            'fields' => PlatformSettings::FIELDS,
            'values' => collect(PlatformSettings::FIELDS)->map(fn ($f) => config($f[0])),
            'plans' => collect(config('shopify.billing.plans'))->map(fn ($p) => $p['name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $plans = array_keys((array) config('shopify.billing.plans'));
        $data = $request->validate([
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'warn_at' => ['required', 'integer', 'min:10', 'max:100'],
            'test_shops' => ['nullable', 'string', 'max:5000'],
            'test_shop_plan' => ['required', 'in:'.implode(',', $plans)],
            'count_test_orders_for' => ['nullable', 'string', 'max:5000'],
        ]);
        $domains = fn ($text) => array_values(array_unique(array_filter(array_map(fn ($d) => strtolower(trim($d)), preg_split('/[\s,]+/', (string) $text)), fn ($d) => preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $d))));

        PlatformSettings::set('grace_days', (int) $data['grace_days']);
        PlatformSettings::set('warn_at', round($data['warn_at'] / 100, 2));
        PlatformSettings::set('test_shops', $domains($data['test_shops'] ?? ''));
        PlatformSettings::set('test_shop_plan', $data['test_shop_plan']);
        PlatformSettings::set('count_test_orders_for', $domains($data['count_test_orders_for'] ?? ''));
        AuditLog::record('admin.settings_saved', null, ['by' => $request->user()->email]);

        return back()->with('status', 'Platform settings saved.');
    }
}
