<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use App\Services\Shopify\Billing;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Store $store, Billing $billing): View
    {
        // Merchants land here after choosing a plan on Shopify's page. If the
        // webhook hasn't arrived yet, ask Shopify directly (only while unsubscribed).
        if (! $store->hasPlanAccess()) {
            try {
                $billing->sync($store);
                $store->refresh();
            } catch (Throwable $e) {
                report($e);
            }
        }

        $checklist = [
            ['label' => 'Connect your store', 'done' => $store->isInstalled() && $store->missingScopes() === [], 'route' => 'app.settings.store'],
            ['label' => 'Choose your goal', 'done' => $store->goal !== null, 'route' => 'app.onboarding'],
            ['label' => 'Choose a plan', 'done' => $store->hasPlanAccess(), 'route' => 'app.settings.billing'],
            ['label' => 'Create your first experience', 'done' => $store->experiences()->exists(), 'route' => 'app.cro.experiences.create'],
            ['label' => 'Place it in the Theme Editor', 'done' => $store->experiences()->where('placement_status', 'placed')->exists(), 'route' => 'app.cro.experiences.index'],
            ['label' => 'Verify analytics', 'done' => false],
        ];

        $alerts = [];
        if ($store->missingScopes() !== []) {
            $alerts[] = ['tone' => 'critical', 'text' => 'Your Shopify connection needs attention. Open Settings → Store to review it.', 'route' => 'app.settings.store', 'action' => 'Review connection'];
        }
        if ($store->capability('online_store_2') === false) {
            $alerts[] = ['tone' => 'warning', 'text' => 'Your current theme doesn\'t support app blocks. OrderOrbit storefront blocks need an Online Store 2.0 theme.', 'route' => 'app.settings.store', 'action' => 'View details'];
        }

        return view('app.dashboard', [
            'store' => $store,
            'checklist' => $checklist,
            'checklistDone' => collect($checklist)->every('done'),
            'alerts' => $alerts,
            'recent' => AuditLog::with('actor')->where('store_id', $store->id)->latest('id')->limit(5)->get(),
        ]);
    }
}
