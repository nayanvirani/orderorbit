<?php

namespace App\Services\Reviews;

use App\Models\Store;
use App\Services\Shopify\AdminApi;
use App\Support\Features;
use Throwable;

/**
 * Review apps, step 1 (G30): each offer product's average rating and review count, read from what
 * the review app already saves on the product in Shopify: the standard reviews.rating and
 * reviews.rating_count fields (Judge.me, Yotpo and most others) or Loox's loox.avg_rating and
 * loox.num_reviews. Published with the offers; nothing can be typed in (App Store rule 1.1.4).
 */
class ProductRatings
{
    public function __construct(private readonly AdminApi $api) {}

    public static function enabled(Store $store): bool
    {
        return Features::enabled('review_apps', $store) && $store->planIncludes('review_apps');
    }

    /**
     * product id (number as string) => [average out of 5, review count], for every product the
     * given published data mentions. Products without reviews are left out.
     *
     * @return array<string, array{0: float, 1: int}>
     */
    public function forData(Store $store, array $data): array
    {
        if (! self::enabled($store)) {
            return [];
        }
        preg_match_all('#gid://shopify/Product/(\d+)#', json_encode($data, JSON_UNESCAPED_SLASHES), $m);
        $ids = array_values(array_unique($m[1]));
        $out = [];
        foreach (array_chunk(array_slice($ids, 0, 1000), 100) as $chunk) {
            try {
                $nodes = $this->api->graphql($store, 'query ($ids: [ID!]!) { nodes(ids: $ids) { ... on Product { id
                    rating: metafield(namespace: "reviews", key: "rating") { value } count: metafield(namespace: "reviews", key: "rating_count") { value }
                    looxRating: metafield(namespace: "loox", key: "avg_rating") { value } looxCount: metafield(namespace: "loox", key: "num_reviews") { value } } } }',
                    ['ids' => array_map(fn ($id) => "gid://shopify/Product/{$id}", $chunk)])['nodes'] ?? [];
            } catch (Throwable $e) {
                report($e);

                continue;
            }
            foreach ((array) $nodes as $n) {
                if ($r = self::parse($n)) {
                    $out[(string) last(explode('/', (string) $n['id']))] = $r;
                }
            }
        }

        return $out;
    }

    /** [average out of 5, count] from a product's review fields, or null when it has no reviews. */
    public static function parse(?array $n): ?array
    {
        if (! $n) {
            return null;
        }
        $rating = null;
        $raw = $n['rating']['value'] ?? null;
        if ($raw !== null) {
            // A rating field: {"value": "4.6", "scale_min": "1.0", "scale_max": "5.0"}.
            $r = json_decode((string) $raw, true);
            if (is_array($r) && is_numeric($r['value'] ?? null)) {
                $max = is_numeric($r['scale_max'] ?? null) && (float) $r['scale_max'] > 0 ? (float) $r['scale_max'] : 5.0;
                $rating = (float) $r['value'] / $max * 5;
            } elseif (is_numeric($raw)) {
                $rating = (float) $raw;
            }
        }
        $count = $n['count']['value'] ?? null;
        if ($rating === null && is_numeric($n['looxRating']['value'] ?? null)) {
            $rating = (float) $n['looxRating']['value'];
            $count = $n['looxCount']['value'] ?? null;
        }
        $count = is_numeric($count) ? (int) $count : 0;

        return $rating !== null && $count > 0 && $rating > 0 ? [round(min(5, $rating), 1), $count] : null;
    }
}
