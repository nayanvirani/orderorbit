<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Models\Store;
use App\Services\Analytics\Events;
use App\Services\SalesPop\RecentOrders;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Collects events from the Growvia web pixel: sessions, Shopify's standard storefront
 * events, experience views, clicks and adds to cart, and completed checkouts attributed to the
 * offers that added each line. Posts are plain text (no CORS preflight) and carry the store's
 * pixel token. Every event carries the anonymous visitor and session, device and traffic source.
 */
class PixelController extends Controller
{
    public function collect(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $ok = response('', 204)->header('Access-Control-Allow-Origin', '*');
        if (! is_array($data)) {
            return $ok;
        }

        $store = Store::where('shop_domain', (string) ($data['s'] ?? ''))->first();
        if (! $store || ! $store->pixel_token || ! hash_equals($store->pixel_token, (string) ($data['t'] ?? ''))) {
            return $ok;
        }

        // Events that fire together at page load arrive as one batch: {t, s, b: [event, …]}.
        $events = isset($data['b']) && is_array($data['b']) ? array_slice(array_filter($data['b'], 'is_array'), 0, 25) : [$data];
        foreach ($events as $event) {
            $this->one($store, $event);
        }

        return $ok;
    }

    /** One event from the pixel. */
    private function one(Store $store, array $data): void
    {
        $base = ['store_id' => $store->id, 'occurred_at' => now()] + $this->context($data);
        // Settings → Privacy: without customer journeys, no customer ids are kept.
        if (! $store->privacy('journeys')) {
            unset($base['customer_id']);
        }
        $data['e'] = preg_replace('/^(growvia|orderorbit):/', '', (string) ($data['e'] ?? ''));

        // Growvia events can start workflows ("Growvia event" trigger).
        if (($data['k'] ?? null) === 'e' && in_array($data['e'], ['survey_answered', 'reward_unlocked', 'upsell_accepted', 'added_to_cart'], true)) {
            $label = isset($data['a']) ? mb_substr(trim(strip_tags((string) $data['a'])), 0, 120) : null;
            defer(fn () => app(\App\Automation\Triggers::class)->event($store, $data['e'], $label, self::handle($data['x'] ?? null)));
        }

        match ($data['k'] ?? null) {
            's' => AnalyticsEvent::create($base + ['event' => 'session', 'name' => 'session_started']),
            'v' => $store->privacy('browsing_events') ? $this->standard($data, $base) : null,
            'e' => $this->experience($data, $base),
            'o' => $this->order($store, $data, $base),
            default => null,
        };
    }

    /** Visitor, session, customer, device, page and traffic source, cleaned. */
    private function context(array $data): array
    {
        $id = fn ($v, $len = 64) => is_string($v) && preg_match('/^[A-Za-z0-9_\-]{1,'.$len.'}$/', $v) ? $v : null;
        $text = fn ($v, $len) => is_scalar($v) && trim((string) $v) !== '' ? mb_substr(mb_strtolower(trim(strip_tags((string) $v))), 0, $len) : null;
        $customer = preg_match('/(\d{1,20})$/', (string) ($data['oc'] ?? $data['cust'] ?? ''), $m) ? $m[1] : null;

        return array_filter([
            'visitor_id' => $id($data['vid'] ?? null),
            'session_id' => $id($data['sid'] ?? null),
            'customer_id' => $customer,
            'device' => in_array($data['dev'] ?? null, ['mobile', 'tablet', 'desktop'], true) ? $data['dev'] : null,
            'page_type' => isset($data['path']) && is_string($data['path']) ? Events::pageType(mb_substr($data['path'], 0, 300)) : null,
            'source' => $text($data['src'] ?? null, 60),
            'medium' => $text($data['med'] ?? null, 60),
            'campaign' => $text($data['cmp'] ?? null, 100),
        ], fn ($v) => $v !== null);
    }

    private static function handle(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^[A-Za-z0-9_\-:]{1,64}$/', $v) ? explode(':', $v)[0] : null;
    }

    private static function numericId(mixed $v): ?string
    {
        return preg_match('/(\d{1,20})$/', (string) $v, $m) ? $m[1] : null;
    }

    /** Shopify's standard storefront events. */
    private function standard(array $data, array $base): void
    {
        $name = (string) ($data['n'] ?? '');
        if (! isset(Events::STANDARD[$name]) || in_array($name, ['session_started', 'checkout_completed'], true)) {
            return;
        }
        $label = isset($data['lb']) && is_scalar($data['lb']) ? mb_substr(trim(strip_tags((string) $data['lb'])), 0, 120) : null;

        AnalyticsEvent::create($base + [
            'event' => 'std',
            'name' => $name,
            'product_id' => self::numericId($data['p'] ?? null),
            'variant_id' => self::numericId($data['va'] ?? null),
            'quantity' => max(1, min(1000, (int) ($data['q'] ?? 1))),
            'value' => isset($data['v']) && is_numeric($data['v']) ? round(max(0, (float) $data['v']), 2) : 0,
            // The product, collection or search term (public catalogue data, or what was searched).
            'label' => $label ?: null,
            'properties' => isset($data['cid']) && self::numericId($data['cid']) ? ['collection_id' => self::numericId($data['cid'])] : null,
        ]);
    }

    /** Growvia events from experiences. */
    private function experience(array $data, array $base): void
    {
        $code = Events::CODES[$data['e']] ?? null;
        $handle = self::handle($data['x'] ?? null);
        if (! $code || ! $handle) {
            return;
        }
        $type = is_string($data['ty'] ?? null) && preg_match('/^[a-z0-9\-]{1,40}$/', $data['ty']) ? $data['ty'] : null;

        AnalyticsEvent::create($base + [
            'event' => $code,
            'name' => Events::nameFor($data['e'], $type),
            'experience_handle' => $handle,
            'experience_type' => $type,
            'template' => is_string($data['tp'] ?? null) && preg_match('/^[a-z0-9\-]{1,64}$/', $data['tp']) ? $data['tp'] : null,
            'quantity' => max(1, min(100, (int) ($data['q'] ?? 1))),
            'experiment_handle' => is_string($data['xp'] ?? null) && preg_match('/^x[a-z0-9]{1,31}$/', $data['xp']) ? $data['xp'] : null,
            'variant' => in_array($data['xv'] ?? null, ['A', 'B', 'C'], true) ? $data['xv'] : null,
            // Survey answers (the only events with a label); plain text, trimmed.
            'label' => isset($data['a']) ? mb_substr(trim(strip_tags((string) $data['a'])), 0, 120) ?: null : null,
        ]);
    }

    private function order(Store $store, array $data, array $base): void
    {
        $ref = mb_substr((string) ($data['id'] ?? ''), 0, 120);
        if ($ref === '') {
            return;
        }
        $currency = strtoupper(mb_substr((string) ($data['c'] ?? ''), 0, 3)) ?: null;
        $country = is_string($data['cc'] ?? null) && preg_match('/^[A-Z]{2}$/', $data['cc']) ? $data['cc'] : null;

        // Record each order once (the thank-you page can fire twice).
        if (AnalyticsEvent::where('store_id', $store->id)->where('event', 'order')->where('order_ref', $ref)->exists()) {
            return;
        }
        try {
            AnalyticsEvent::create($base + ['event' => 'order', 'name' => 'checkout_completed', 'order_ref' => $ref, 'value' => round((float) ($data['v'] ?? 0), 2), 'currency' => $currency, 'country' => $country,
                'quantity' => max(1, (int) array_sum(array_map(fn ($l) => (int) ($l['q'] ?? 1), array_slice((array) ($data['l'] ?? []), 0, 250))))]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        // Revenue per experience: the lines each offer added.
        $credited = [];
        foreach (array_slice((array) ($data['l'] ?? []), 0, 250) as $line) {
            $who = self::handle($line['o'] ?? null) ?? self::handle($line['b'] ?? null);
            if ($who) {
                $credited[$who]['value'] = ($credited[$who]['value'] ?? 0) + (float) ($line['v'] ?? 0);
                $credited[$who]['quantity'] = ($credited[$who]['quantity'] ?? 0) + (int) ($line['q'] ?? 1);
            }
        }
        foreach ($credited as $who => $c) {
            AnalyticsEvent::create($base + ['event' => 'attributed', 'name' => 'growvia:revenue_attributed', 'experience_handle' => $who, 'order_ref' => $ref, 'value' => round($c['value'], 2), 'quantity' => max(1, $c['quantity']), 'currency' => $currency, 'country' => $country]);
        }

        $this->purchases($store, $ref, $data);
    }

    /**
     * Products from the order for Sales pop: title, link, image and the order's market country.
     */
    private function purchases(Store $store, string $ref, array $data): void
    {
        $products = array_map(fn ($line) => [
            'id' => (string) ($line['p'] ?? ''),
            'title' => (string) ($line['t'] ?? ''),
            'url' => $line['u'] ?? null,
            'image' => $line['i'] ?? null,
        ], array_slice((array) ($data['l'] ?? []), 0, 50));

        RecentOrders::record($store, $ref, $products, $data['cc'] ?? null);
    }
}
