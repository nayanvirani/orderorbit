<?php

namespace App\Services\Experiences;

use App\Experiences\BundleSchema;
use App\Experiences\GiftSchema;
use App\Experiences\Registry;
use App\Models\Experience;
use App\Models\RecentPurchase;
use App\Models\Store;
use App\Services\SalesPop\RecentOrders;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Publishes the store's live experiences to an app-owned metafield on the app
 * installation. The theme app extension reads it in Liquid
 * (app.metafields.orderorbit.experiences), so nothing is injected into the theme
 * and the storefront never calls our servers to render.
 */
class StorefrontPublisher
{
    public const NAMESPACE = 'orderorbit';

    public const KEY = 'experiences';

    public function __construct(
        private readonly AdminApi $api,
        private readonly OfferSync $offers,
        private readonly BundleSync $bundles,
    ) {}

    public function payload(Store $store): array
    {
        $experiences = Experience::with('publishedVersion')
            ->where('store_id', $store->id)
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get()
            ->filter(fn (Experience $e) => $e->publishedVersion !== null && Registry::has($e->type))
            ->map(function (Experience $e) use ($store) {
                $config = $e->publishedVersion->config;
                if ($e->type === 'bundles') {
                    $config = BundleSchema::payload(BundleSchema::normalize($config)[0]) + ['analytics' => $config['analytics'] ?? []];
                } elseif ($e->type === 'progressive-gifts') {
                    $config = GiftSchema::payload(GiftSchema::normalize($config)[0]) + ['analytics' => $config['analytics'] ?? []];
                }

                if ($e->type === 'sales-pop') {
                    // Real recent purchases come from OrderOrbit's public feed for this store.
                    $config['content']['feed'] = route('sales-pop.feed', ['shop' => $store->shop_domain, 'days' => $config['content']['max_age_days'] ?? 7]);
                }

                if ($e->type === 'countdown') {
                    // Daily cutoffs count in the store's time zone.
                    $config['content']['tz'] = $store->timezone ?: 'UTC';
                }

                return [
                    'id' => $e->handle,
                    'type' => $e->type,
                    'template' => $e->publishedVersion->template_key,
                    'style' => Registry::template($e->type, $e->publishedVersion->template_key)['style'] ?? 'card',
                    'version' => $e->publishedVersion->version,
                    'priority' => (int) ($config['behavior']['priority'] ?? 50),
                    'starts_at' => $e->starts_at?->toIso8601String(),
                    'ends_at' => $e->ends_at?->toIso8601String(),
                    'content' => $config['content'] ?? [],
                    'design' => $config['design'] ?? [],
                    'behavior' => $config['behavior'] ?? [],
                    'targeting' => $config['targeting'] ?? [],
                    'analytics' => $config['analytics'] ?? [],
                ];
            })
            ->sortByDesc('priority')
            ->values()
            ->all();

        return [
            'v' => 1,
            'currency' => $store->currency,
            'updated_at' => now()->toIso8601String(),
            'experiences' => $experiences,
        ];
    }

    public function sync(Store $store): array
    {
        // Bundles and savings go live (or stop) before the storefront starts (or stops) promising them.
        $this->bundles->sync($store);
        $this->offers->sync($store);

        $payload = $this->payload($store);
        $this->primeSalesPop($store, $payload);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (strlen($json) > 1_500_000) {
            Log::warning('Storefront payload is close to the metafield size limit', ['store' => $store->shop_domain, 'bytes' => strlen($json)]);
        }

        $installation = $this->api->graphql($store, '{ currentAppInstallation { id } }')['currentAppInstallation']['id'];

        $result = $this->api->graphql($store, <<<'GQL'
            mutation Publish($metafields: [MetafieldsSetInput!]!) {
              metafieldsSet(metafields: $metafields) { userErrors { field message } }
            }
            GQL, ['metafields' => [[
            'ownerId' => $installation,
            'namespace' => self::NAMESPACE,
            'key' => self::KEY,
            'type' => 'json',
            'value' => $json,
        ]]]);

        if (! empty($result['metafieldsSet']['userErrors'])) {
            throw new RuntimeException('Publishing failed: '.$result['metafieldsSet']['userErrors'][0]['message']);
        }

        return $payload;
    }

    /**
     * A newly published Sales pop starts with the store's recent orders instead of waiting
     * for the next one.
     */
    private function primeSalesPop(Store $store, array $payload): void
    {
        if (! collect($payload['experiences'])->contains('type', 'sales-pop') || ! $store->hasScope('read_orders')
            || RecentPurchase::where('store_id', $store->id)->exists()) {
            return;
        }

        try {
            app(RecentOrders::class)->import($store);
        } catch (\Throwable $e) {
            Log::info('Sales pop: importing recent orders failed', ['store' => $store->shop_domain, 'error' => $e->getMessage()]);
        }
    }
}
