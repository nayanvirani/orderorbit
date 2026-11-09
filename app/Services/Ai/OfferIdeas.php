<?php

namespace App\Services\Ai;

use App\Models\Profit\ProfitOrder;
use App\Models\Store;
use App\Support\Features;

/**
 * AI offer ideas (G20): products customers buy together (from orders, no AI) as bundle ideas, and
 * offer text written by AI on request. Nothing is saved until the merchant chooses a
 * suggestion and saves the offer.
 */
class OfferIdeas
{
    /** A pair needs at least this many shared orders to be suggested. */
    public const MIN_ORDERS = 3;

    public function __construct(private readonly Ai $ai) {}

    public static function available(Store $store): bool
    {
        return Features::enabled('ai_offer_ideas', $store) && $store->planIncludes('ai_offer_ideas');
    }

    /** Products bought in the same order over 90 days: [[a, b, orders, share of a's orders]], most first. */
    public function pairs(Store $store, int $limit = 10): array
    {
        $orders = ProfitOrder::with('lines:id,profit_order_id,product_id,title')->where('store_id', $store->id)->where('excluded', false)
            ->where('day', '>=', now($store->timezone ?: 'UTC')->subDays(89)->toDateString())->get(['id']);
        $names = [];
        $count = [];
        $together = [];
        foreach ($orders as $o) {
            $products = [];
            foreach ($o->lines as $l) {
                if ($l->product_id) {
                    $products[(int) $l->product_id] = true;
                    $names[(int) $l->product_id] ??= explode(' - ', (string) $l->title)[0];
                }
            }
            $ids = array_keys($products);
            sort($ids);
            foreach ($ids as $id) {
                $count[$id] = ($count[$id] ?? 0) + 1;
            }
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $key = $ids[$i].':'.$ids[$j];
                    $together[$key] = ($together[$key] ?? 0) + 1;
                }
            }
        }
        arsort($together);
        $out = [];
        foreach ($together as $key => $n) {
            if ($n < self::MIN_ORDERS || count($out) >= $limit) {
                break;
            }
            [$a, $b] = array_map('intval', explode(':', $key));
            $out[] = ['a' => ['id' => $a, 'name' => $names[$a]], 'b' => ['id' => $b, 'name' => $names[$b]], 'orders' => $n,
                'share' => (int) round($n / max(1, min($count[$a], $count[$b])) * 100)];
        }

        return $out;
    }

    /**
     * Three options for an offer's text, or null without AI. $context: what the offer is (type,
     * products, current text); only product names and the merchant's own words are sent.
     */
    public function copy(Store $store, array $context): ?array
    {
        $data = $this->ai->json($store, 'offer_copy',
            'You write short, honest storefront offer text for a Shopify store. No made-up numbers, reviews, scarcity or urgency; '
            .'only what the offer really is. Headline up to 60 characters, subheadline up to 120, button up to 24. Match the store\'s language and tone from the current text.',
            'Offer: '.json_encode(array_intersect_key($context, array_flip(['type', 'products', 'discount', 'headline', 'subheadline', 'cta'])), JSON_UNESCAPED_UNICODE)
            ."\nStore: ".($store->name ?: 'the store').'. Write 3 different options.',
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['options'], 'properties' => [
                'options' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['headline', 'subheadline', 'cta'], 'properties' => [
                    'headline' => ['type' => 'string'], 'subheadline' => ['type' => 'string'], 'cta' => ['type' => 'string'],
                ]]],
            ]], 1500);
        if (! $data) {
            return null;
        }

        return collect($data['options'] ?? [])->take(3)->map(fn ($o) => [
            'headline' => mb_substr(trim((string) $o['headline']), 0, 60), 'subheadline' => mb_substr(trim((string) $o['subheadline']), 0, 120), 'cta' => mb_substr(trim((string) $o['cta']), 0, 24),
        ])->filter(fn ($o) => $o['headline'] !== '')->values()->all();
    }
}
