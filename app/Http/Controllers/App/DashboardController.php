<?php

namespace App\Http\Controllers\App;

use App\Experiences\Registry;
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

        $byType = $store->experiences()->where('status', '!=', 'archived')
            ->selectRaw('type, status, count(*) as total')->groupBy('type', 'status')->get();
        $features = collect(Registry::features())->map(fn ($feature) => $feature + [
            'description' => Registry::type($feature['types'][0])['description'],
            'live' => (int) $byType->whereIn('type', $feature['types'])->where('status', 'published')->sum('total'),
            'total' => (int) $byType->whereIn('type', $feature['types'])->sum('total'),
        ])->all();

        return view('app.dashboard', [
            'store' => $store,
            'features' => $features,
            'checklist' => $checklist,
            'checklistDone' => collect($checklist)->every('done'),
            'alerts' => $alerts,
            'recent' => AuditLog::with('actor')->where('store_id', $store->id)->latest('id')->limit(5)->get(),
        ]);
    }
}
