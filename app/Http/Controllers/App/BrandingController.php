<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CroSetting;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Spa\Page;

/**
 * Settings → Branding (section 40): design tokens every new experience starts from.
 */
class BrandingController extends Controller
{
    public function show(Store $store): Page
    {
        return page('settings/branding', ['branding' => CroSetting::brandingFor($store), 'currency' => $store->currency ?? 'USD']);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        if ($request->input('action') === 'reset') {
            CroSetting::updateOrCreate(['store_id' => $store->id], ['branding' => CroSetting::BRANDING_DEFAULTS]);
            AuditLog::record('branding.reset', $store);

            return redirect()->to(app_route('app.settings.branding', ['notice' => 'saved']));
        }

        $branding = [];
        foreach (['primary_color', 'accent_color', 'text_color', 'background_color'] as $key) {
            $value = strtolower((string) $request->input($key));
            if (! preg_match('/^#[0-9a-f]{6}$/', $value)) {
                return redirect()->to(app_route('app.settings.branding', ['notice' => 'invalid_color']));
            }
            $branding[$key] = $value;
        }
        $branding['button_style'] = $request->input('button_style') === 'outline' ? 'outline' : 'filled';
        $branding['radius'] = max(0, min(32, (int) $request->input('radius', 12)));
        $branding['font'] = $request->input('font') === 'system' ? 'system' : 'theme';

        CroSetting::updateOrCreate(['store_id' => $store->id], ['branding' => $branding]);
        AuditLog::record('branding.saved', $store, $branding);

        return redirect()->to(app_route('app.settings.branding', ['notice' => 'saved']));
    }
}
