<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public const GOALS = [
        'conversion' => ['label' => 'Increase conversion', 'help' => 'Turn more visitors into buyers with trust, urgency and sticky add-to-cart.'],
        'aov' => ['label' => 'Increase order value', 'help' => 'Bundles, quantity breaks, free gifts and upsells.'],
        'repeat' => ['label' => 'Drive repeat purchases', 'help' => 'Thank You offers, reorder reminders and win-back flows.'],
        'checkout' => ['label' => 'Improve checkout', 'help' => 'Trust, shipping progress and offers in checkout where supported.'],
    ];

    public function show(Store $store): View
    {
        return view('app.onboarding', ['store' => $store, 'goals' => self::GOALS]);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate(['goal' => 'required|in:'.implode(',', array_keys(self::GOALS))]);

        $store->forceFill(['goal' => $data['goal']])->save();
        AuditLog::record('onboarding.goal_selected', $store, $data, 'store_user', (string) $request->attributes->get('storeUser')?->id);

        return redirect()->to(app_route('app.billing', ['saved' => 1]));
    }
}
