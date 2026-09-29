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

    /** Section 41 / D1: the eight onboarding steps. */
    public const STEPS = [
        'Store connection', 'Goal', 'First experience', 'Template', 'Configure', 'Preview', 'Publish and place', 'Verify analytics',
    ];

    public function show(Request $request, Store $store): View
    {
        $step = (int) $request->query('step', $store->goal ? 2 : 1);

        return view('app.onboarding', [
            'store' => $store,
            'goals' => self::GOALS,
            'steps' => self::STEPS,
            'step' => max(1, min(2, $step)),
            'missingScopes' => $store->missingScopes(),
        ]);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $goal = (string) $request->input('goal');

        if (! array_key_exists($goal, self::GOALS)) {
            return redirect()->to(app_route('app.onboarding', ['step' => 2, 'notice' => 'unexpected']));
        }

        $store->forceFill(['goal' => $goal])->save();
        AuditLog::record('onboarding.goal_selected', $store, ['goal' => $goal]);

        return redirect()->to(app_route($store->hasPlanAccess() ? 'app.dashboard' : 'app.settings.billing', ['notice' => 'saved']));
    }
}
