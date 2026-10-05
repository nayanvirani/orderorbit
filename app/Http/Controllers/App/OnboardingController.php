<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Experiences\Registry;
use App\Support\Spa\Page;

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
        'Store connection', 'Goal', 'First widget', 'Template', 'Configure', 'Preview', 'Publish and place', 'Verify analytics',
    ];

    /** Recommended first experiences for each goal: [label, help, route, params]. */
    public const RECOMMENDED = [
        'conversion' => [
            ['Trust badges', 'Reviews, guarantees and secure-checkout badges near the buy button.', 'app.cro.experiences.create', ['type' => 'trust']],
            ['Sticky add to cart', 'Keeps the buy button in reach as shoppers scroll.', 'app.cro.experiences.create', ['type' => 'sticky-atc']],
            ['Countdown timer', 'Real deadlines for real campaigns.', 'app.cro.experiences.create', ['type' => 'countdown']],
        ],
        'aov' => [
            ['Bundle', 'Quantity breaks, mix & match or fixed bundles.', 'app.bundles.types', []],
            ['Progressive gifts', 'Free shipping and gifts that unlock as the cart grows.', 'app.gifts.models', []],
            ['Cart upsell', 'One last add-on in the cart.', 'app.cro.experiences.create', ['type' => 'cart-upsells']],
        ],
        'repeat' => [
            ['Thank You reorder', 'Order the same items again in one tap.', 'app.cro.experiences.create', ['type' => 'ty-reorder']],
            ['Next-order discount', 'A code for the next purchase on the Thank You page.', 'app.cro.experiences.create', ['type' => 'ty-discount']],
            ['Review request workflow', 'Ask for a review after delivery (Scale).', 'app.automation.templates', []],
        ],
        'checkout' => [
            ['Checkout trust', 'Shipping, returns and guarantees inside checkout.', 'app.cro.experiences.create', ['type' => 'checkout-trust']],
            ['Shipping progress in checkout', 'Shows how close shoppers are to free shipping.', 'app.cro.experiences.create', ['type' => 'checkout-shipping']],
            ['Thank You cross-sell', 'Recommend the next product after purchase.', 'app.cro.experiences.create', ['type' => 'ty-cross-sell']],
        ],
    ];

    public function show(Request $request, Store $store): Page
    {
        $first = $store->experiences()->oldest('id')->first();
        $events = \App\Models\AnalyticsEvent::where('store_id', $store->id)->exists();
        // Progress through the eight steps, from what the store has actually done.
        $done = [
            1 => $store->isInstalled() && $store->missingScopes() === [],
            2 => $store->goal !== null,
            3 => (bool) $first,
            4 => (bool) $first,
            5 => $first && ($first->status === 'published' || ! $first->has_unpublished_changes || $first->updated_at->gt($first->created_at->addSeconds(5))),
            6 => $first && $first->status === 'published',
            7 => $first && $first->status === 'published' && in_array($first->placement_status, ['placed', 'external'], true),
            8 => $events,
        ];
        $default = collect($done)->search(false) ?: 8;
        $step = max(1, min(8, (int) $request->query('step', $default)));

        $lastEvent = \App\Models\AnalyticsEvent::where('store_id', $store->id)->max('occurred_at');
        $surface = $first && Registry::has($first->type) ? Registry::type($first->type)['surface'] : 'product';

        return page('onboarding', [
            'goals' => self::GOALS,
            'goal' => $store->goal,
            'steps' => self::STEPS,
            'step' => $step,
            'done' => $done,
            'store' => ['name' => $store->name ?? $store->shop_domain, 'domain' => $store->shop_domain, 'currency' => $store->currency, 'currencies' => $store->capability('presentment_currencies') ?? []],
            'missingScopes' => $store->missingScopes(),
            'recommended' => array_map(fn ($r) => ['label' => $r[0], 'help' => $r[1], 'href' => route($r[2], $r[3] + ['onboarding' => 1], false)], self::RECOMMENDED[$store->goal ?? 'aov']),
            'first' => $first ? [
                'name' => $first->name, 'status' => $first->status, 'placement' => str_replace('_', ' ', $first->placement_status ?? 'not checked'),
                'type' => Registry::has($first->type) ? Registry::type($first->type)['label'] : $first->type,
                'edit' => match ($first->type) {
                    'bundles' => route('app.bundles.edit', ['bundle' => $first->id], false),
                    'progressive-gifts' => route('app.gifts.edit', ['gift' => $first->id], false),
                    default => route('app.cro.experiences.edit', ['experience' => $first->id], false),
                },
                'editorUrl' => $store->themeEditorUrl($surface), 'editorLabel' => $store->editorLabel($surface),
            ] : null,
            'pixelConnected' => (bool) $store->web_pixel_id,
            'lastEvent' => $lastEvent ? \Illuminate\Support\Carbon::parse($lastEvent) : null,
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

        return redirect()->to($store->hasPlanAccess() ? app_route('app.onboarding', ['step' => 3, 'notice' => 'saved']) : app_route('app.settings.billing', ['notice' => 'saved']));
    }
}
