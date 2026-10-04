<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Customer Journey: one shopper's path, from first visit through experiences, cart and checkout
 * to purchases, repeat purchases and the automations that followed. Shoppers are identified only
 * by Shopify's anonymous visitor id and, when signed in or after buying, their customer id.
 */
class Journeys
{
    /** Shoppers active in the period, most recent first; by default only those who bought. */
    public function list(Store $store, Carbon $from, Carbon $to, bool $buyersOnly = true): LengthAwarePaginator
    {
        $query = AnalyticsEvent::where('store_id', $store->id)->whereBetween('occurred_at', [$from, $to])->whereNotNull('visitor_id')
            ->selectRaw("visitor_id, min(occurred_at) as first_seen, max(occurred_at) as last_seen, count(distinct session_id) as sessions,
                sum(case when event = 'order' then 1 else 0 end) as orders, sum(case when event = 'order' then value else 0 end) as revenue,
                max(customer_id) as customer_id, max(source) as source")
            ->groupBy('visitor_id');
        if ($buyersOnly) {
            $query->havingRaw("sum(case when event = 'order' then 1 else 0 end) > 0");
        }

        $page = $query->orderByDesc('last_seen')->paginate(50)->withQueryString();

        // Orders across all of a customer's devices, for the "repeat" badge.
        $customers = $page->getCollection()->pluck('customer_id')->filter()->unique()->values();
        $orders = $customers->isEmpty() ? collect() : AnalyticsEvent::where('store_id', $store->id)->where('event', 'order')->whereIn('customer_id', $customers->all())
            ->selectRaw('customer_id, count(*) as n')->groupBy('customer_id')->pluck('n', 'customer_id');
        $page->getCollection()->each(fn ($p) => $p->setAttribute('total_orders', max((int) $p->orders, (int) ($orders[$p->customer_id] ?? 0))));

        return $page;
    }

    /**
     * A shopper's events, grouped into sessions. Automation events (by customer id) have no session.
     *
     * @return array{visitors: list<string>, customer: ?string, sessions: list<array>, orders: int, revenue: float, first_seen: ?Carbon}
     */
    public function show(Store $store, string $visitor): array
    {
        $customer = AnalyticsEvent::where('store_id', $store->id)->where('visitor_id', $visitor)->whereNotNull('customer_id')->value('customer_id');
        // The same customer on other browsers or devices.
        $visitors = $customer
            ? AnalyticsEvent::where('store_id', $store->id)->where('customer_id', $customer)->whereNotNull('visitor_id')->distinct()->limit(20)->pluck('visitor_id')->push($visitor)->unique()->values()
            : collect([$visitor]);

        $events = AnalyticsEvent::where('store_id', $store->id)
            ->where(fn ($q) => $q->whereIn('visitor_id', $visitors->all())->when($customer, fn ($q) => $q->orWhere(fn ($q) => $q->where('customer_id', $customer)->where('event', 'auto'))))
            ->where('event', '!=', 'session')
            ->orderBy('occurred_at')->orderBy('id')->limit(2000)->get();

        $orderNumber = 0;
        $sessions = $events->groupBy(fn ($e) => $e->session_id ?: 'auto-'.$e->occurred_at->format('YmdH'))->map(function (Collection $rows) use (&$orderNumber) {
            $first = $rows->first();

            return [
                'started' => $first->occurred_at,
                'source' => $rows->pluck('source')->filter()->first(),
                'device' => $rows->pluck('device')->filter()->first(),
                'automation' => $first->event === 'auto' && ! $first->session_id,
                'events' => $rows->map(function ($e) use (&$orderNumber) {
                    if ($e->event === 'order') {
                        $orderNumber++;
                    }

                    return [
                        'at' => $e->occurred_at,
                        'name' => $e->name,
                        'label' => Events::label($e->name),
                        'kind' => $this->kind($e),
                        'detail' => $e->label,
                        'experience' => $e->experience_handle,
                        'page_type' => $e->page_type,
                        'value' => $e->value,
                        'currency' => $e->currency,
                        'order_number' => $e->event === 'order' ? $orderNumber : null,
                        'run_id' => $e->properties['run_id'] ?? null,
                    ];
                })->all(),
            ];
        })->values()->all();

        $orders = $events->where('event', 'order');

        return [
            'visitors' => $visitors->all(),
            'customer' => $customer,
            'sessions' => $sessions,
            'orders' => $orders->count(),
            'revenue' => (float) $orders->sum('value'),
            'currency' => $orders->first()?->currency ?? $store->currency,
            'first_seen' => $events->first()?->occurred_at,
        ];
    }

    private function kind(AnalyticsEvent $e): string
    {
        return match (true) {
            $e->event === 'order' => 'purchase',
            $e->event === 'auto' => 'automation',
            $e->experience_handle !== null => 'experience',
            in_array($e->name, ['product_added_to_cart', 'product_removed_from_cart', 'cart_viewed', 'checkout_started', 'payment_info_submitted'], true) => 'cart',
            default => 'browse',
        };
    }

    /** Deletes everything held for a customer (customers/redact) and the browsers they used. */
    public static function forget(Store $store, string $customer): int
    {
        $visitors = AnalyticsEvent::where('store_id', $store->id)->where('customer_id', $customer)->whereNotNull('visitor_id')->distinct()->pluck('visitor_id');
        $deleted = AnalyticsEvent::where('store_id', $store->id)->where('customer_id', $customer)->delete();
        foreach ($visitors->chunk(500) as $chunk) {
            $deleted += AnalyticsEvent::where('store_id', $store->id)->whereIn('visitor_id', $chunk->all())->delete();
        }

        return $deleted;
    }
}
