<?php

namespace App\Http\Controllers;

use App\Models\RecentPurchase;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The public feed Sales pop reads on the storefront: products from the store's real
 * recent orders, with the order's country and time. Served only while the store has a
 * published Sales pop, cached briefly, and safe to cache publicly.
 */
class SalesPopController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $shop = strtolower((string) $request->query('shop', ''));
        $days = max(1, min(RecentPurchase::MAX_DAYS, (int) $request->query('days', 7)));

        $items = preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $shop)
            ? Cache::remember("sales-pop:{$shop}:{$days}", 60, fn () => $this->items($shop, $days))
            : [];

        return response()->json(['items' => $items])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=60');
    }

    private function items(string $shop, int $days): array
    {
        $store = Store::where('shop_domain', $shop)->whereNull('uninstalled_at')->first();
        if (! $store || ! $store->experiences()->where('type', 'sales-pop')->where('status', 'published')->exists()) {
            return [];
        }

        return RecentPurchase::where('store_id', $store->id)
            ->where('purchased_at', '>=', now()->subDays($days))
            ->orderByDesc('purchased_at')
            ->limit(40)
            ->get()
            ->map(fn (RecentPurchase $p) => array_filter([
                't' => $p->title,
                'u' => $p->url,
                'i' => $p->image,
                'c' => $p->country,
                'at' => $p->purchased_at->toIso8601String(),
            ]))
            ->all();
    }
}
