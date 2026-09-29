<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Store $store): View
    {
        $checklist = [
            ['label' => 'Connect your store', 'done' => $store->isInstalled()],
            ['label' => 'Choose your goal', 'done' => $store->goal !== null, 'route' => 'app.onboarding'],
            ['label' => 'Choose a plan', 'done' => $store->plan !== null, 'route' => 'app.billing'],
            ['label' => 'Create your first experience', 'done' => false],
            ['label' => 'Place it in the Theme Editor', 'done' => false],
            ['label' => 'Verify analytics', 'done' => false],
        ];

        return view('app.dashboard', [
            'store' => $store,
            'checklist' => $checklist,
            'checklistDone' => collect($checklist)->every('done'),
        ]);
    }
}
