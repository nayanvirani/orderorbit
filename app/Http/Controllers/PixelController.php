<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Services\SalesPop\RecentOrders;
use App\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Collects events from the OrderOrbit Space web pixel: sessions, experience views,
 * clicks and adds to cart, and completed checkouts attributed to the offers that
 * added each line. Posts are plain text (no CORS preflight) and carry the store's
 * pixel token.
 */
class PixelController extends Controller
{
    private const EVENTS = [
        'experience_viewed' => 'view', 'experience_clicked' => 'click', 'added_to_cart' => 'add',
        'reward_unlocked' => 'unlock', 'upsell_accepted' => 'accept', 'upsell_declined' => 'decline',
    ];

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

        $handle = fn ($v) => is_string($v) && preg_match('/^[A-Za-z0-9_\-:]{1,64}$/', $v) ? explode(':', $v)[0] : null;
        $base = ['store_id' => $store->id, 'occurred_at' => now()];
        $data['e'] = str_replace('orderorbit:', '', (string) ($data['e'] ?? ''));

        match ($data['k'] ?? null) {
            's' => AnalyticsEvent::create($base + ['event' => 'session']),
            'e' => isset(self::EVENTS[$data['e'] ?? '']) && $handle($data['x'] ?? null)
                ? AnalyticsEvent::create($base + ['event' => self::EVENTS[$data['e']], 'experience_handle' => $handle($data['x']), 'quantity' => max(1, min(100, (int) ($data['q'] ?? 1)))])
                : null,
            'o' => $this->order($store, $data, $base, $handle),
            default => null,
        };

        return $ok;
    }

    private function order(Store $store, array $data, array $base, callable $handle): void
    {
        $ref = mb_substr((string) ($data['id'] ?? ''), 0, 120);
        if ($ref === '') {
            return;
        }
        $currency = strtoupper(mb_substr((string) ($data['c'] ?? ''), 0, 3)) ?: null;

        // Record each order once (the thank-you page can fire twice).
        if (AnalyticsEvent::where('store_id', $store->id)->where('event', 'order')->where('order_ref', $ref)->exists()) {
            return;
        }
        try {
            AnalyticsEvent::create($base + ['event' => 'order', 'order_ref' => $ref, 'value' => round((float) ($data['v'] ?? 0), 2), 'currency' => $currency]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        // Revenue per experience: the lines each offer added.
        $credited = [];
        foreach (array_slice((array) ($data['l'] ?? []), 0, 250) as $line) {
            $who = $handle($line['o'] ?? null) ?? $handle($line['b'] ?? null);
            if ($who) {
                $credited[$who]['value'] = ($credited[$who]['value'] ?? 0) + (float) ($line['v'] ?? 0);
                $credited[$who]['quantity'] = ($credited[$who]['quantity'] ?? 0) + (int) ($line['q'] ?? 1);
            }
        }
        foreach ($credited as $who => $c) {
            AnalyticsEvent::create($base + ['event' => 'attributed', 'experience_handle' => $who, 'order_ref' => $ref, 'value' => round($c['value'], 2), 'quantity' => max(1, $c['quantity']), 'currency' => $currency]);
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
