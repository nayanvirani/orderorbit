<?php

namespace App\Services;

use App\Models\Store;
use App\Models\UsageRecord;
use Closure;

/**
 * Plan usage meters (section 47). Feature modules register how to count their
 * items as they are built; unregistered meters count as zero.
 */
class Usage
{
    /** Every plan limit, set per plan (and per store) in the super admin. */
    public const METERS = [
        'active_experiences' => 'Live widgets (all types)',
        'bundles' => 'Bundles',
        'free_gifts' => 'Free-gift campaigns',
        'shipping_bars' => 'Shipping bars',
        'product_upsells' => 'Product upsells',
        'cart_upsells' => 'Cart upsells',
        'countdowns' => 'Countdown timers',
        'sticky_atc' => 'Sticky add to cart',
        'trust' => 'Trust & social proof blocks',
        'preorders' => 'Pre-orders',
        'sales_pop' => 'Sales pop',
        'checkout_blocks' => 'Checkout blocks',
        'thank_you_blocks' => 'Thank You & Order Status blocks',
        'post_purchase' => 'Post-purchase offers',
        'account_blocks' => 'Customer account blocks',
        'running_tests' => 'Running A/B tests',
        'personalization_rules' => 'Personalization rules',
        'segments' => 'Segments',
        'workflows' => 'Active workflows',
        'automation_executions' => 'Automation runs per month',
    ];

    /** Group headings for the limits, in order. */
    public const GROUPS = [
        'Storefront widgets' => ['active_experiences', 'bundles', 'free_gifts', 'shipping_bars', 'product_upsells', 'cart_upsells', 'countdowns', 'sticky_atc', 'trust', 'preorders', 'sales_pop'],
        'Checkout & account blocks' => ['checkout_blocks', 'thank_you_blocks', 'post_purchase', 'account_blocks'],
        'Testing & personalization' => ['running_tests', 'personalization_rules', 'segments'],
        'Automation' => ['workflows', 'automation_executions'],
    ];

    /** The module (App\Support\Modules) each limit belongs to: with the module off, the limit is 0. */
    public const FEATURE = [
        'bundles' => 'bundles', 'free_gifts' => 'progressive_gifts', 'shipping_bars' => 'progressive_gifts',
        'product_upsells' => 'product_upsells', 'cart_upsells' => 'cart_upsells', 'countdowns' => 'countdown',
        'sticky_atc' => 'sticky_atc', 'trust' => 'trust', 'preorders' => 'preorder', 'sales_pop' => 'sales_pop',
        'checkout_blocks' => 'checkout', 'thank_you_blocks' => 'thank_you', 'post_purchase' => 'post_purchase',
        'account_blocks' => 'customer_accounts', 'running_tests' => 'ab_testing', 'personalization_rules' => 'personalization',
        'segments' => 'personalization', 'workflows' => 'automation', 'automation_executions' => 'automation',
    ];

    /** What each limit counts, where it isn't obvious from the name. */
    public const HELP = [
        'active_experiences' => 'How many widgets can be live at the same time, of any type (bundles, countdowns, checkout blocks…). Leave empty to rely only on the limits per type below.',
        'free_gifts' => 'Progressive gift and free-gift campaigns.',
        'bundles' => 'All bundle types, including BOGO and quantity breaks.',
        'segments' => 'Saved audience segments (not archived).',
        'automation_executions' => 'Workflow runs started this calendar month.',
    ];

    /** The limit an experience type counts toward (null: none of its own). */
    public static function meterFor(string $type): ?string
    {
        return match (true) {
            in_array($type, ['bundles', 'bogo', 'quantity-breaks'], true) => 'bundles',
            in_array($type, ['progressive-gifts', 'free-gifts'], true) => 'free_gifts',
            $type === 'shipping-bar' => 'shipping_bars',
            $type === 'product-upsells' => 'product_upsells',
            $type === 'cart-upsells' => 'cart_upsells',
            $type === 'countdown' => 'countdowns',
            $type === 'sticky-atc' => 'sticky_atc',
            $type === 'trust' => 'trust',
            $type === 'preorder' => 'preorders',
            $type === 'sales-pop' => 'sales_pop',
            $type === 'post-purchase' => 'post_purchase',
            default => match (\App\Experiences\Registry::has($type) ? \App\Experiences\Registry::type($type)['surface'] : null) {
                'checkout' => 'checkout_blocks',
                'thank-you' => 'thank_you_blocks',
                'account' => 'account_blocks',
                default => null,
            },
        };
    }

    /** @return array<string, list<string>> meter => experience types counted by it */
    public static function experienceMeters(): array
    {
        static $meters;

        return $meters ??= collect(array_keys(\App\Experiences\Registry::types()))->groupBy(fn ($type) => self::meterFor($type) ?? '')->forget('')->map->values()->map->all()->all();
    }

    /** @var array<string, Closure(Store): int> */
    private array $counters = [];

    public function register(string $meter, Closure $counter): void
    {
        $this->counters[$meter] = $counter;
    }

    public function current(Store $store, string $meter): int
    {
        if (isset($this->counters[$meter])) {
            return ($this->counters[$meter])($store);
        }

        // Live offers. Strict: everything published counts (a plan change pauses what it doesn't allow).
        if ($meter === 'active_experiences') {
            return \App\Models\Experience::where('store_id', $store->id)->where('status', 'published')->count();
        }
        if ($types = self::experienceMeters()[$meter] ?? null) {
            return \App\Models\Experience::where('store_id', $store->id)->where('status', 'published')->whereIn('type', $types)->count();
        }

        if ($meter === 'automation_executions') {
            return (int) UsageRecord::where('store_id', $store->id)
                ->where('meter', $meter)
                ->where('period_start', now()->startOfMonth()->toDateString())
                ->value('quantity');
        }

        return 0;
    }

    /**
     * Whether the store can add $adding more of $meter on its current plan.
     */
    public function allows(Store $store, string $meter, int $adding = 1): bool
    {
        if (! $store->hasPlanAccess()) {
            return false;
        }

        $limit = $store->planLimit($meter);

        return $limit === null || $this->current($store, $meter) + $adding <= $limit;
    }

    /** "The Free plan includes 1 running A/B test. …" for a limit that's been reached. */
    public static function limitMessage(Store $store, string $meter): string
    {
        $plan = config('shopify.billing.plans.'.$store->effectivePlan().'.name') ?: 'current';
        $limit = $store->hasPlanAccess() ? (int) $store->planLimit($meter) : 0;
        $what = mb_strtolower(self::METERS[$meter] ?? $meter);

        return $limit === 0
            ? ucfirst($what)." aren't included in the {$plan} plan. Upgrade to use them."
            : "The {$plan} plan includes {$limit} ".($limit === 1 ? rtrim(preg_replace('/s\b/', '', $what, 1)) : $what).'. Switch one off or upgrade for more.';
    }

    public function increment(Store $store, string $meter, int $by = 1): void
    {
        $record = UsageRecord::firstOrCreate(
            ['store_id' => $store->id, 'meter' => $meter, 'period_start' => now()->startOfMonth()->toDateString()],
            ['quantity' => 0],
        );
        $record->increment('quantity', $by);
    }

    /**
     * @return list<array{meter: string, label: string, used: int, limit: ?int}>
     */
    public function summary(Store $store): array
    {
        return array_map(fn ($meter, $label) => [
            'meter' => $meter,
            'label' => $label,
            'used' => $this->current($store, $meter),
            'limit' => $store->hasPlanAccess() ? $store->planLimit($meter) : 0,
            'help' => self::HELP[$meter] ?? null,
        ], array_keys(self::METERS), self::METERS);
    }
}
