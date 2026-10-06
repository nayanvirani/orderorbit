<?php

namespace App\Support;

use App\Experiences\Registry;
use App\Models\CroSetting;
use App\Services\Experiences\TemplateLibrary;
use Throwable;

/**
 * The public template gallery: the app's own templates (Registry), exactly as merchants get
 * them, grouped under the website's feature pages. Previews are drawn in the browser by the
 * storefront runtime and the checkout previews, the same code the app uses.
 */
class TemplateGallery
{
    /** App feature (resources/experiences/features.php) => website feature page. */
    public const FEATURES = [
        'bundles' => 'bundles', 'progressive-gifts' => 'progressive-gifts', 'cart-upsells' => 'cart-upsells',
        'countdown' => 'countdown-timer', 'sticky-atc' => 'sticky-add-to-cart', 'preorder' => 'preorder',
        'sales-pop' => 'sales-pop', 'trust' => 'trust-social-proof', 'checkout' => 'checkout', 'thank-you' => 'checkout',
        'post-purchase' => 'checkout', 'customer-accounts' => 'customer-accounts',
    ];

    /** @return list<array{type: string, key: string, name: string, label: string, surface: string, feature: string}> */
    public static function all(): array
    {
        static $list;
        if ($list !== null) {
            return $list;
        }
        $list = [];
        foreach (Registry::features() as $featureKey => $feature) {
            $slug = self::FEATURES[$featureKey] ?? null;
            foreach ($slug ? ($feature['types'] ?? []) : [] as $type) {
                if (! Registry::has($type) || ! empty(Registry::type($type)['retired'])) {
                    continue;
                }
                try {
                    $templates = Registry::offered($type);
                } catch (Throwable) {
                    $templates = Registry::templates($type);
                }
                foreach ($templates as $key => $template) {
                    $list[] = ['type' => $type, 'key' => $key, 'name' => $template['name'], 'label' => Registry::type($type)['label'], 'surface' => Registry::type($type)['surface'], 'feature' => $slug];
                }
            }
        }

        return $list;
    }

    /** Templates shown on the public website per feature; the rest are only in the app. */
    public const PUBLIC_PER_FEATURE = 3;

    /**
     * The public showcase: a few templates per feature, taken in turn from each of its block types,
     * so visitors see the range without the whole library being on the open web.
     *
     * @return list<array{type: string, key: string, name: string, label: string, surface: string, feature: string}>
     */
    public static function showcase(): array
    {
        $byFeature = [];
        foreach (self::all() as $t) {
            $byFeature[$t['feature']][$t['type']][] = $t;
        }
        $out = [];
        foreach ($byFeature as $types) {
            $picked = [];
            for ($round = 0; count($picked) < self::PUBLIC_PER_FEATURE; $round++) {
                $added = false;
                foreach ($types as $list) {
                    if (isset($list[$round]) && count($picked) < self::PUBLIC_PER_FEATURE) {
                        $picked[] = $list[$round];
                        $added = true;
                    }
                }
                if (! $added) {
                    break;
                }
            }
            array_push($out, ...$picked);
        }

        return $out;
    }

    /** Templates per website feature that are only shown inside the app. @return array<string, int> */
    public static function hiddenCounts(): array
    {
        $hidden = array_count_values(array_column(self::all(), 'feature'));
        foreach (self::showcase() as $t) {
            $hidden[$t['feature']]--;
        }

        return $hidden;
    }

    /** The render-ready preview of a template, in neutral sample branding. */
    public static function preview(string $type, string $key): array
    {
        return TemplateLibrary::preview($type, $key, ['accent_color' => '#5b45f0', 'text_color' => '#15123b'] + CroSetting::BRANDING_DEFAULTS);
    }
}
