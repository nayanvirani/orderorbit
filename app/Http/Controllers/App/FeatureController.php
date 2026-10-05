<?php

namespace App\Http\Controllers\App;

use App\Experiences\Registry;
use App\Http\Controllers\Controller;
use App\Models\CroSetting;
use App\Models\Store;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Http\RedirectResponse;
use App\Support\Spa\Page;

/**
 * One page per app feature (Bundles, Volume discounts, BOGO, ...): what it does,
 * how it works, the store's offers of that kind and templates to start from.
 */
class FeatureController extends Controller
{
    public function show(Store $store, string $feature): Page|RedirectResponse
    {
        $definition = Registry::feature($feature) ?? abort(404);
        if (isset($definition['module'])) {
            // Bundles and Progressive gifts have their own screens.
            return redirect()->to(app_route($definition['module']));
        }
        $types = $definition['types'];
        app(\App\Services\Experiences\PlacementDetector::class)->refreshIfStale($store);

        $experiences = $store->experiences()
            ->whereIn('type', $types)
            ->where('status', '!=', 'archived')
            ->latest('updated_at')
            ->get();

        $branding = CroSetting::brandingFor($store);
        $templates = collect($types)->flatMap(fn ($type) => collect(Registry::offered($type))->map(fn ($template, $key) => [
            'type' => $type,
            'key' => $key,
            'name' => $template['name'],
            'preview' => TemplateLibrary::preview($type, $key, $branding),
        ]))->values();

        $defs = collect($types)->mapWithKeys(fn ($type) => [$type => Registry::type($type)]);
        $surface = $defs->first()['surface'];
        $discounts = $defs->contains(fn ($t) => $t['discount'] ?? false);
        $counts = $experiences->countBy('status');

        return page('cro/feature', [
            'key' => $feature,
            'feature' => ['label' => $definition['label'], 'lower' => lower_label($definition['label']), 'tagline' => $definition['tagline'], 'lead' => strip_tags($definition['lead']),
                'icon' => $definition['icon'] ?? null, 'tone' => $definition['tone'] ?? null, 'steps' => $definition['steps']],
            'types' => $defs->map(fn ($t, $key) => ['key' => $key, 'label' => $t['label'], 'singular' => lower_label($t['singular']), 'empty' => $t['empty'] ?? null])->values(),
            'experiences' => $experiences->map(fn ($e) => ExperienceController::row($e) + ['discount' => (bool) $e->shopify_discount_id]),
            'counts' => ['published' => (int) ($counts['published'] ?? 0), 'draft' => (int) ($counts['draft'] ?? 0), 'paused' => (int) ($counts['paused'] ?? 0)],
            'discounts' => $discounts,
            'templates' => $templates->map(fn ($t) => $t + ['type_label' => $defs[$t['type']]['label']]),
            'editor' => ['url' => $store->themeEditorUrl($surface), 'label' => $store->editorLabel($surface)],
            'checkout' => in_array($surface, \App\Experiences\Schema::CHECKOUT_SURFACES, true) || $surface === 'account',
            // Only offer what Shopify allows: blocks inside checkout need Shopify Plus (or a development store).
            'notice' => match (true) {
                $surface === 'checkout' && ! $store->capability('checkout_blocks') => 'plus',
                $surface === 'account' && $store->capability('new_customer_accounts') === false => 'accounts',
                in_array($surface, \App\Experiences\Schema::CHECKOUT_SURFACES, true) && ! $store->planIncludes(Store::surfacePlanFeature($surface)) => 'plan',
                default => null,
            },
            'accountsUrl' => $store->adminUrl('settings/customer_accounts'),
            'docsUrl' => route('site.docs', ['bundles' => 'bundles', 'progressive-gifts' => 'progressive-gifts', 'checkout' => 'checkout-blocks', 'thank-you' => 'checkout-blocks', 'post-purchase' => 'checkout-blocks', 'customer-accounts' => 'customer-accounts'][$feature] ?? 'storefront-widgets'),
        ]);
    }
}
