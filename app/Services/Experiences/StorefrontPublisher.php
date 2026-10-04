<?php

namespace App\Services\Experiences;

use App\Experiences\BundleSchema;
use App\Experiences\GiftSchema;
use App\Experiences\Registry;
use App\Experiences\Schema;
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

    /** Shop metafield read by the checkout UI extension ($app namespace = app-owned). */
    public const CHECKOUT_NAMESPACE = '$app';

    public const CHECKOUT_KEY = 'checkout';

    /**
     * The theme's experiences (storefront blocks and the app embed).
     */
    public function payload(Store $store): array
    {
        $experiences = collect($this->live($store))
            ->reject(fn (array $e) => in_array(Registry::type($e['type'])['surface'], Schema::CHECKOUT_SURFACES, true))
            ->values()->all();

        return [
            'v' => 1,
            'currency' => $store->currency,
            'updated_at' => now()->toIso8601String(),
            'experiences' => $experiences,
        ];
    }

    /**
     * What the checkout UI extension reads: checkout, Thank You and Order Status blocks, plus the
     * live Progressive gifts campaign that the shipping-progress and free-gift blocks follow.
     */
    public function checkoutPayload(Store $store): array
    {
        $live = collect($this->live($store));

        return [
            'v' => 1,
            'currency' => $store->currency,
            'updated_at' => now()->toIso8601String(),
            'experiences' => $live->filter(fn (array $e) => in_array(Registry::type($e['type'])['surface'], Schema::CHECKOUT_SURFACES, true))->values()->all(),
            'gifts' => ($gifts = $live->firstWhere('type', 'progressive-gifts')) ? array_intersect_key($gifts, array_flip(['id', 'content'])) : null,
        ];
    }

    /**
     * Every live experience in the storefront payload shape, highest priority first.
     */
    private function live(Store $store): array
    {
        return Experience::with('publishedVersion')
            ->where('store_id', $store->id)
            // Over the plan's sales limit past the grace period: nothing shows until the plan fits again.
            ->when($store->offersSuspended(), fn ($q) => $q->whereRaw('1 = 0'))
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

        $owners = $this->api->graphql($store, '{ currentAppInstallation { id } shop { id } }');
        $metafields = [[
            'ownerId' => $owners['currentAppInstallation']['id'],
            'namespace' => self::NAMESPACE,
            'key' => self::KEY,
            'type' => 'json',
            'value' => $json,
        ]];
        // Checkout extensions can't read app-installation metafields, so their blocks go on the shop.
        if (! empty($owners['shop']['id'])) {
            $metafields[] = [
                'ownerId' => $owners['shop']['id'],
                'namespace' => self::CHECKOUT_NAMESPACE,
                'key' => self::CHECKOUT_KEY,
                'type' => 'json',
                'value' => json_encode($this->checkoutPayload($store), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }

        $result = $this->api->graphql($store, <<<'GQL'
            mutation Publish($metafields: [MetafieldsSetInput!]!) {
              metafieldsSet(metafields: $metafields) { userErrors { field message } }
            }
            GQL, ['metafields' => $metafields]);

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
