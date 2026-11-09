<?php

namespace App\Services\Ai;

use App\Models\Profit\ProfitOrder;
use App\Models\Store;
use App\Support\Features;

/**
 * Offer ideas (G20): products customers buy together (from orders, no AI) as bundle ideas, and
 * offer text on request. The text is written by Growvia from the offer itself (no AI) unless AI is
 * switched on in Internal Admin and available for the store; then AI writes it, and if AI fails
 * Growvia's own options are used. Nothing is saved until the merchant chooses one and saves.
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
     * Three options for an offer's text: ['options' => [...], 'source' => 'ai'|'auto']. $context:
     * what the offer is (type, products, discount, current text); AI gets only product names and
     * the merchant's own words.
     */
    public function suggest(Store $store, array $context): array
    {
        $options = $this->ai->available($store) ? $this->copy($store, $context) : null;

        return $options ? ['options' => $options, 'source' => 'ai'] : ['options' => self::auto($context), 'source' => 'auto'];
    }

    /** Growvia's own three options, from the offer's products, type and discount. No AI. */
    public static function auto(array $context): array
    {
        $short = fn (string $name) => mb_strlen($name) > 28 ? rtrim(mb_substr($name, 0, 27)).'…' : $name;
        $products = array_values(array_filter(array_map(fn ($p) => $short(trim((string) $p)), (array) ($context['products'] ?? []))));
        $discount = trim((string) ($context['discount'] ?? ''));
        $save = $discount !== '' ? "Save {$discount}" : null;
        $type = (string) ($context['type'] ?? '');
        $list = match (count($products)) {
            0 => null, 1 => $products[0], 2 => "{$products[0]} and {$products[1]}",
            default => implode(', ', array_slice($products, 0, -1)).' and '.end($products),
        };

        if (count($products) >= 2) {
            $all = count($products) === 2 ? 'both' : 'all '.count($products);
            $options = [
                [$save ? "{$save} on the set" : 'Better together', "Get {$list} together.", 'Add '.$all],
                ['Complete the set', $save ? "{$list}: {$discount} off when bought together." : "{$list} in one click.", 'Add the set'],
                ['Pair '.$products[0], 'Goes well with '.implode(' and ', array_slice($products, 1)).'.', $save ? "Add and save {$discount}" : 'Add to cart'],
            ];
        } elseif ($list) {
            $options = [
                ['You may also like', $list.($save ? " · {$discount} off" : ''), 'Add to cart'],
                [$save ? "{$save} on {$list}" : "Add {$list}", $save ? "Add it to this order for {$discount} less." : 'Add it to this order in one click.', 'Add'],
                ['Complete your order', "Add {$list} before you check out.", 'Add to order'],
            ];
        } else {
            $gift = str_contains($type, 'gift') || str_contains($type, 'tier') || str_contains($type, 'reward');
            $options = $gift ? [
                ['Unlock your reward', 'Add a little more to your cart to get it.', 'Shop now'],
                ['A thank-you on us', 'Spend a little more and the reward is added to your order.', 'See what\'s next'],
                ['Almost there', 'Your reward is waiting a few items away.', 'Keep shopping'],
            ] : [
                [$save ? "{$save} today" : 'Something to add?', $save ? "Take {$discount} off when you add it to this order." : 'Picked to go with what\'s in your cart.', 'Add to cart'],
                ['Complete your order', 'A good match for what you\'ve chosen.', 'Add'],
                ['Before you go', $save ? "{$discount} off when you add it now." : 'Add it in one click.', 'Add to order'],
            ];
        }

        return array_map(fn ($o) => ['headline' => mb_substr($o[0], 0, 60), 'subheadline' => mb_substr($o[1], 0, 120), 'cta' => mb_substr($o[2], 0, 24)], $options);
    }

    /** Three AI-written options, or null when AI didn't answer. */
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
