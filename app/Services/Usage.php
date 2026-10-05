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
    public const METERS = [
        'active_experiences' => 'Active experiences',
        'bundles' => 'Bundles',
        'free_gifts' => 'Free-gift campaigns',
        'cart_upsells' => 'Cart upsells',
        'preorders' => 'Pre-orders',
        'shipping_bars' => 'Shipping bars',
        'workflows' => 'Workflows',
        'automation_executions' => 'Automation executions this month',
    ];

    /** What each limit counts, where it isn't obvious from the name. */
    public const HELP = [
        'active_experiences' => 'Live countdowns, sticky add to cart, trust and social proof, sales pop, product upsells and checkout blocks. Bundles, gift campaigns, shipping bars, cart upsells and pre-orders have their own limits.',
    ];

    /** Experience types counted by "Active experiences": the ones without a limit of their own. */
    public static function countsAsActive(string $type): bool
    {
        return \App\Experiences\Registry::has($type) && empty(\App\Experiences\Registry::type($type)['meter']);
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
