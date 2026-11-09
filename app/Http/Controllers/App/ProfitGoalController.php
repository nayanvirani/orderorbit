<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use App\Services\Profit\ProfitGoals;
use App\Services\Profit\ProfitSync;
use App\Support\Features;
use App\Support\Plans;
use App\Support\Spa\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Profit › Goals (G6): the monthly goal, this month's pacing and the goal settings. */
class ProfitGoalController extends Controller
{
    public function show(Store $store, ProfitGoals $goals): Page
    {
        abort_unless(Features::enabled('profit_goals', $store) && ProfitSync::enabled($store), 404);
        $missing = ProfitSync::missingAccess($store);

        return page('profit/goals', [
            'status' => $missing ? null : $goals->status($store), 'goal' => ProfitGoals::goal($store),
            'types' => ProfitGoals::TYPES, 'alerts' => ProfitGoals::ALERTS, 'currency' => $store->currency ?: 'USD',
            'access' => $missing ? ['missing' => $missing] : null,
            'locked' => $store->planIncludes('profit_goals') ? null : Plans::unlockMessage('profit_goals', 'set goals', $store),
        ]);
    }

    public function save(Request $request, Store $store): RedirectResponse
    {
        abort_unless(Features::enabled('profit_goals', $store) && ProfitSync::enabled($store), 404);
        abort_unless($store->planIncludes('profit_goals'), 403, Plans::unlockMessage('profit_goals', 'set goals', $store));
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ProfitGoals::TYPES))],
            'amount' => ['required', 'numeric', 'gt:0', $request->input('type') === 'margin' ? 'max:100' : 'max:1000000000'],
            'alert' => ['required', Rule::in(ProfitGoals::ALERTS)],
        ], ['amount.gt' => 'Enter a goal above 0.', 'amount.max' => 'A margin goal is at most 100%.']);
        $settings = ProfitSetting::for($store->id);
        // A changed goal can warn again this month.
        $settings->forceFill(['goal' => ['type' => $data['type'], 'amount' => round((float) $data['amount'], 2), 'alert' => (int) $data['alert']]])->save();

        return redirect()->to(app_route('app.profit.goals', ['notice' => 'saved']));
    }
}
