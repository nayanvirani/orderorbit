<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Store $store): View
    {
        $checklist = [
            ['label' => 'Connect your store', 'done' => $store->isInstalled() && $store->missingScopes() === [], 'route' => 'app.settings.store'],
            ['label' => 'Choose your goal', 'done' => $store->goal !== null, 'route' => 'app.onboarding'],
            ['label' => 'Choose a plan', 'done' => $store->plan !== null, 'route' => 'app.settings.billing'],
            ['label' => 'Create your first experience', 'done' => false],
            ['label' => 'Place it in the Theme Editor', 'done' => false],
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
