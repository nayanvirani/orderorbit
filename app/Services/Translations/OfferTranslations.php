<?php

namespace App\Services\Translations;

use App\Models\Experience;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use App\Support\Features;
use App\Support\OptionalAccess;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Widget translations (G22): the text in each live offer, per store language. Translations keep
 * the original text they were made from; when the merchant changes the original, the old
 * translation stops showing (the original shows) until it's updated. The storefront gets one
 * copy of the offers per language, so switching language needs no republishing.
 */
class OfferTranslations
{
    /** Content keys that are never shopper-facing text. */
    private const SKIP = ['id', 'handle', 'url', 'href', 'link', 'feed', 'tz', 'image', 'images', 'icon', 'icons', 'color', 'colour', 'bg', 'background',
        'template', 'mode', 'type', 'position', 'layout', 'style', 'align', 'font', 'size', 'variant', 'variants', 'product', 'products', 'collection',
        'collections', 'discount_type', 'currency', 'custom_css', 'css', 'selector', 'analytics', 'code', 'hide', 'placement', 'trigger', 'unit', 'units',
        'date', 'ends_at', 'starts_at', 'cutoff', 'days', 'shape', 'theme', 'preset', 'gid', 'sku'];

    public function __construct(private readonly AdminApi $api) {}

    public static function enabled(Store $store): bool
    {
        return Features::enabled('translations', $store) && $store->planIncludes('translations') && OptionalAccess::granted($store, 'read_locales');
    }

    /** The store's published languages other than the default: [code => name]. */
    public function languages(Store $store): array
    {
        if (! OptionalAccess::granted($store, 'read_locales')) {
            return [];
        }

        return Cache::remember("shop-locales:{$store->id}", 3600, function () use ($store) {
            try {
                $locales = $this->api->graphql($store, '{ shopLocales { locale name primary published } }')['shopLocales'] ?? [];
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return collect($locales)->filter(fn ($l) => $l['published'] && ! $l['primary'])->mapWithKeys(fn ($l) => [$l['locale'] => $l['name']])->all();
        });
    }

    /** Shopper-facing text in an offer's content: [dotted path => text]. */
    public static function texts(array $content, string $prefix = ''): array
    {
        $out = [];
        foreach ($content as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (! is_int($key) && in_array(strtolower((string) $key), self::SKIP, true)) {
                continue;
            }
            if (is_array($value)) {
                $out += self::texts($value, $path);
            } elseif (is_string($value) && self::isText($value)) {
                $out[$path] = $value;
            }
        }

        return $out;
    }

    private static function isText(string $v): bool
    {
        $v = trim($v);

        return $v !== '' && mb_strlen($v) <= 1000 && preg_match('/\p{L}/u', $v) && ! preg_match('#^(https?:|//|gid://|\#[0-9a-f]{3,8}$|rgba?\()#i', $v);
    }

    /** One language's copy of the published offers: translated text where it's up to date. */
    public function apply(array $experiences, array $translations, string $locale): array
    {
        foreach ($experiences as $i => $e) {
            $map = $translations[$e['id']][$locale] ?? [];
            if (! $map || empty($e['content'])) {
                continue;
            }
            foreach ($map as $path => [$source, $text]) {
                $current = data_get($e['content'], $path);
                if (is_string($current) && $current === $source && trim((string) $text) !== '') {
                    data_set($experiences[$i]['content'], $path, $text);
                }
            }
        }

        return $experiences;
    }

    /** handle => translations, for the store's offers that have any. */
    public static function forStore(Store $store): array
    {
        return Experience::where('store_id', $store->id)->whereNotNull('translations')->get(['handle', 'translations'])
            ->mapWithKeys(fn ($e) => [$e->handle => (array) $e->translations])->all();
    }
}
