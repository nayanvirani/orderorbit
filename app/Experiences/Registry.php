<?php

namespace App\Experiences;

use InvalidArgumentException;

/**
 * The CRO experience types and their templates (resources/experiences/types.php).
 */
class Registry
{
    private static ?array $types = null;

    private static ?array $features = null;

    /** The new menu's type names: [label, singular] (labels only). */
    public const NEW_TYPE_NAMES = [
        'progressive-gifts' => ['Spend and unlock rewards', 'Rewards'], 'cart-upsells' => ['Cart recommendations', 'Cart recommendation'],
        'sticky-atc' => ['Sticky buy button', 'Sticky buy button'], 'preorder' => ['Pre-orders', 'Pre-order'], 'sales-pop' => ['Recent sales pop-ups', 'Recent sales pop-up'],
        'post-purchase' => ['One-click offer after payment', 'One-click offer after payment'],
    ];

    private static ?array $renamedTypes = null;

    public static function types(): array
    {
        $types = self::$types ??= require resource_path('experiences/types.php');
        if (! self::newMenu()) {
            return $types;
        }
        if (self::$renamedTypes === null) {
            self::$renamedTypes = $types;
            foreach (self::NEW_TYPE_NAMES as $key => [$label, $singular]) {
                self::$renamedTypes[$key]['label'] = $label;
                self::$renamedTypes[$key]['singular'] = $singular;
            }
        }

        return self::$renamedTypes;
    }

    /**
     * Types merchants can create now (retired ones keep working but live in the Bundles and
     * Progressive gifts modules).
     */
    public static function creatable(): array
    {
        return array_filter(self::types(), fn ($t) => empty($t['retired']));
    }

    public static function has(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::types());
    }

    public static function type(string $type): array
    {
        return self::types()[$type] ?? throw new InvalidArgumentException("Unknown widget type [{$type}].");
    }

    public static function templates(string $type): array
    {
        return self::type($type)['templates'];
    }

    /**
     * Templates offered for new experiences: the Growvia team can unpublish a template in the
     * Internal Admin. Experiences already using it keep working (template() still finds it).
     */
    public static function offered(string $type): array
    {
        $hidden = \Illuminate\Support\Facades\Cache::remember('templates:unpublished', 300, fn () => \App\Models\CroTemplate::where('status', '!=', 'published')->get(['type', 'key'])->map(fn ($t) => $t->type.':'.$t->key)->all());

        return array_filter(self::templates($type), fn ($t, $key) => ! in_array($type.':'.$key, $hidden, true), ARRAY_FILTER_USE_BOTH) ?: self::templates($type);
    }

    public static function template(string $type, string $key): ?array
    {
        return self::templates($type)[$key] ?? null;
    }

    public static function defaultTemplate(string $type): string
    {
        return array_key_first(self::templates($type));
    }

    /**
     * Types whose active experiences count against a usage meter.
     *
     * @return array<string, string> type => meter
     */
    public static function meters(): array
    {
        return array_filter(array_map(fn ($t) => $t['meter'], self::types()));
    }

    /**
     * App features for navigation (resources/experiences/features.php), each grouping one or more types.
     */
    /** The new menu's friendly offer names (labels only: keys, routes and saved offers stay the same). */
    public const NEW_NAMES = [
        'bundles' => 'Bundles and quantity discounts', 'progressive-gifts' => 'Spend and unlock rewards', 'cart-upsells' => 'Cart recommendations',
        'sticky-atc' => 'Sticky buy button', 'preorder' => 'Pre-orders', 'checkout' => 'Checkout extras', 'thank-you' => 'Thank You page extras',
        'post-purchase' => 'One-click offer after payment', 'customer-accounts' => 'Customer account extras', 'sales-pop' => 'Recent sales pop-ups',
    ];

    private static ?array $renamed = null;

    public static function features(): array
    {
        $features = self::$features ??= require resource_path('experiences/features.php');
        if (! self::newMenu()) {
            return $features;
        }

        if (self::$renamed === null) {
            self::$renamed = [];
            foreach ($features as $key => $f) {
                self::$renamed[$key] = ['label' => self::NEW_NAMES[$key] ?? $f['label']] + $f;
            }
        }

        return self::$renamed;
    }

    /** An offer's name before the new menu, for "Was: …" notes. */
    public static function oldLabel(string $key): ?string
    {
        return (self::$features ??= require resource_path('experiences/features.php'))[$key]['label'] ?? null;
    }

    /** Whether the store on this request has the new menu and names (flag new_menu). */
    public static function newMenu(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }
        $attributes = request()->attributes;
        $store = $attributes->get('store');
        if (! $store instanceof \App\Models\Store) {
            return false;
        }
        // Checked once per request and store: type and feature names are read many times per page.
        $memo = $attributes->get('_oo_new_menu');
        if (! is_array($memo) || $memo[0] !== $store->id) {
            $memo = [$store->id, \App\Support\Features::enabled('new_menu', $store)];
            $attributes->set('_oo_new_menu', $memo);
        }

        return $memo[1];
    }

    public static function feature(string $key): ?array
    {
        return self::features()[$key] ?? null;
    }

    public static function featureFor(string $type): ?string
    {
        foreach (self::features() as $key => $feature) {
            if (in_array($type, $feature['types'], true)) {
                return $key;
            }
        }

        return null;
    }
}
