<?php

namespace App\Services\SalesPop;

use App\Models\RecentPurchase;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real recent purchases for Sales pop. Orders arrive from Shopify (orders/create webhook and an
 * import of recent orders) and from the web pixel, which can add the market country. Only the
 * product, the country and the time are kept: never names, emails or addresses.
 */
class RecentOrders
{
    public function __construct(private readonly AdminApi $api) {}

    /**
     * @param  list<array{id: string, title: string, url?: ?string, image?: ?string}>  $products
     */
    public static function record(Store $store, string $orderRef, array $products, ?string $country, ?CarbonInterface $at = null): int
    {
        $orderRef = self::orderId($orderRef);
        $country = preg_match('/^[A-Z]{2}$/', strtoupper((string) $country)) ? strtoupper((string) $country) : null;
        $seen = [];

        foreach ($products as $product) {
            $id = self::orderId((string) ($product['id'] ?? ''));
            $title = trim(mb_substr(strip_tags((string) ($product['title'] ?? '')), 0, 255));
            if ($orderRef === '' || $id === '' || $title === '' || isset($seen[$id]) || count($seen) >= 5) {
                continue;
            }
            $seen[$id] = true;
            $path = parse_url((string) ($product['url'] ?? ''), PHP_URL_PATH);
            $image = (string) ($product['image'] ?? '');

            $row = RecentPurchase::firstOrNew(['store_id' => $store->id, 'order_ref' => $orderRef, 'product_id' => $id]);
            $row->fill(array_filter([
                'title' => $row->title ?: $title,
                'url' => $row->url ?: (is_string($path) && str_starts_with($path, '/') ? mb_substr($path, 0, 500) : null),
                'image' => $row->image ?: (preg_match('#^https://[^\s"\'<>]+$#', $image) ? mb_substr($image, 0, 1000) : null),
                'country' => $row->country ?: $country,
            ], fn ($v) => $v !== null));
            $row->purchased_at ??= $at ?? now();
            $row->save();
        }

        if ($seen) {
            // Keep only the newest purchases per store.
            $keep = RecentPurchase::where('store_id', $store->id)->orderByDesc('purchased_at')->orderByDesc('id')->limit(RecentPurchase::KEEP)->pluck('id');
            RecentPurchase::where('store_id', $store->id)->whereNotIn('id', $keep)->delete();
        }

        return count($seen);
    }

    /** "gid://shopify/Order/123" and "123" are the same order. */
    public static function orderId(string $value): string
    {
        return preg_match('/(\d+)$/', $value, $m) ? $m[1] : '';
    }

    /**
     * From an orders/create webhook (REST payload). Product links and images come from one
     * Admin API lookup.
     */
    public function fromWebhook(Store $store, array $order): int
    {
        if (! empty($order['cancelled_at']) || empty($order['id'])) {
            return 0;
        }
        $lines = [];
        foreach (array_slice($order['line_items'] ?? [], 0, 20) as $line) {
            if (! empty($line['product_id'])) {
                $lines[(string) $line['product_id']] ??= ['id' => (string) $line['product_id'], 'title' => (string) ($line['title'] ?? '')];
            }
        }
        if (! $lines) {
            return 0;
        }

        try {
            $ids = array_map(fn ($id) => "gid://shopify/Product/{$id}", array_slice(array_keys($lines), 0, 5));
            $nodes = $this->api->graphql($store, 'query ($ids: [ID!]!) { nodes(ids: $ids) { ... on Product { id title handle featuredMedia { preview { image { url(transform: {maxWidth: 200}) } } } } } }', ['ids' => $ids])['nodes'] ?? [];
            foreach (array_filter($nodes) as $node) {
                $lines[self::orderId($node['id'])] = self::product($node);
            }
        } catch (Throwable $e) {
            Log::info('Sales pop: product lookup failed', ['store' => $store->shop_domain, 'error' => $e->getMessage()]);
        }

        $country = $order['shipping_address']['country_code'] ?? $order['billing_address']['country_code'] ?? null;

        return self::record($store, (string) $order['id'], array_values($lines), $country, isset($order['created_at']) ? Carbon::parse($order['created_at']) : null);
    }

    /** Shopify hasn't approved the app for protected customer data (order access). */
    public static function blocked(Store $store): bool
    {
        return Cache::has("sales-pop-blocked:{$store->id}");
    }

    /**
     * Imports the store's recent orders (needs read_orders and Shopify's protected customer data
     * approval for orders). Returns the products recorded.
     */
    public function import(Store $store, int $days = RecentPurchase::MAX_DAYS): int
    {
        $since = now()->subDays($days)->toDateString();
        try {
            $data = $this->orders($store, $since);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'not approved to access the Order')) {
                Cache::put("sales-pop-blocked:{$store->id}", true, 3600);
                throw new OrdersBlocked('Shopify has not approved this app to read orders yet.', 0, $e);
            }
            throw $e;
        }
        Cache::forget("sales-pop-blocked:{$store->id}");

        $count = 0;
        foreach (array_reverse($data['orders']['nodes'] ?? []) as $order) {
            $products = array_values(array_filter(array_map(fn ($line) => $line['product'] ? self::product($line['product']) : null, $order['lineItems']['nodes'] ?? [])));
            $count += self::record($store, $order['id'], $products, null, Carbon::parse($order['createdAt']));
        }

        return $count;
    }

    private function orders(Store $store, string $since): array
    {
        return $this->api->graphql($store, <<<'GQL'
            query ($query: String!) {
              orders(first: 50, sortKey: CREATED_AT, reverse: true, query: $query) {
                nodes {
                  id createdAt
                  lineItems(first: 5) { nodes { product { id title handle featuredMedia { preview { image { url(transform: {maxWidth: 200}) } } } } } }
                }
              }
            }
            GQL, ['query' => "created_at:>={$since} AND -status:cancelled"]);
    }

    private static function product(array $node): array
    {
        return [
            'id' => $node['id'],
            'title' => $node['title'] ?? '',
            'url' => isset($node['handle']) ? '/products/'.$node['handle'] : null,
            'image' => $node['featuredMedia']['preview']['image']['url'] ?? null,
        ];
    }
}
