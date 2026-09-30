<?php

namespace App\Http\Controllers\App;

use App\Experiences\Registry;
use App\Http\Controllers\Controller;
use App\Models\CroSetting;
use App\Models\Store;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * One page per app feature (Bundles, Volume discounts, BOGO, ...): what it does,
 * how it works, the store's offers of that kind and templates to start from.
 */
class FeatureController extends Controller
{
    public function show(Store $store, string $feature): View|RedirectResponse
    {
        $definition = Registry::feature($feature) ?? abort(404);
        if (isset($definition['module'])) {
            // Bundles and Progressive gifts have their own screens.
            return redirect()->to(app_route($definition['module']));
        }
        $types = $definition['types'];

        $experiences = $store->experiences()
            ->whereIn('type', $types)
            ->where('status', '!=', 'archived')
            ->latest('updated_at')
            ->get();

        $branding = CroSetting::brandingFor($store);
        $templates = collect($types)->flatMap(fn ($type) => collect(Registry::templates($type))->map(fn ($template, $key) => [
            'type' => $type,
            'key' => $key,
            'name' => $template['name'],
            'preview' => TemplateLibrary::preview($type, $key, $branding),
        ]))->values();

        return view('app.features.show', [
            'store' => $store,
            'key' => $feature,
            'feature' => $definition,
            'types' => collect($types)->mapWithKeys(fn ($type) => [$type => Registry::type($type)])->all(),
            'experiences' => $experiences,
            'counts' => $experiences->countBy('status'),
            'templates' => $templates,
        ]);
    }
}
