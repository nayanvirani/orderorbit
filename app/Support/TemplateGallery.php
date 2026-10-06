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

    /** The render-ready preview of a template, in neutral sample branding. */
    public static function preview(string $type, string $key): array
    {
        return TemplateLibrary::preview($type, $key, ['accent_color' => '#5b45f0', 'text_color' => '#15123b'] + CroSetting::BRANDING_DEFAULTS);
    }
}
