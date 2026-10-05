<?php

namespace App\Services\Audiences;

use App\Experiences\Registry;
use App\Experiences\Schema;
use App\Models\Audiences\PersonalizationRule;
use App\Models\Audiences\Segment;
use App\Models\Automation\Workflow;
use App\Models\Experience;
use App\Models\Experiments\Experiment;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Throwable;

/**
 * Audiences & Personalization (Phase 10). Segments are reusable groups of shoppers defined by
 * rules; they're evaluated in the shopper's browser (oo-audiences.js) from the signed-in
 * customer's orders, spend, tags and purchases, the device, the market and the experiences they
 * used, and on the server for workflows. Rules show, swap the template of, or hide experiences.
 */
class Audiences
{
    public function __construct(private readonly AdminApi $api) {}

    public const FIELDS = [
        'orders_count' => ['label' => 'Number of orders', 'type' => 'number', 'ops' => ['gte' => 'at least', 'lte' => 'at most', 'eq' => 'exactly'], 'search' => 'orders_count'],
        'ltv' => ['label' => 'Total spent (lifetime value)', 'type' => 'money', 'ops' => ['gte' => 'at least', 'lte' => 'at most'], 'search' => 'total_spent'],
        'aov' => ['label' => 'Average order value', 'type' => 'money', 'ops' => ['gte' => 'at least', 'lte' => 'at most']],
        'days_since_order' => ['label' => 'Days since last order', 'type' => 'number', 'ops' => ['gte' => 'at least', 'lte' => 'at most']],
        'customer' => ['label' => 'Shopper', 'type' => 'select', 'ops' => ['is' => 'is'], 'options' => ['signed_in' => 'Signed in', 'guest' => 'Not signed in']],
        'customer_tag' => ['label' => 'Customer tag', 'type' => 'text', 'ops' => ['has' => 'has', 'not' => 'doesn\'t have'], 'search' => 'tag'],
        'product_purchased' => ['label' => 'Bought a product', 'type' => 'products', 'ops' => ['any' => 'bought any of', 'not' => 'never bought']],
        'country' => ['label' => 'Market country', 'type' => 'text', 'ops' => ['in' => 'is one of', 'not' => 'isn\'t one of'], 'help' => 'Two-letter codes separated by commas, e.g. US, CA.'],
        'device' => ['label' => 'Device', 'type' => 'select', 'ops' => ['is' => 'is'], 'options' => ['mobile' => 'Mobile', 'desktop' => 'Desktop']],
        'experience' => ['label' => 'Used a widget', 'type' => 'experience', 'ops' => ['viewed' => 'viewed', 'clicked' => 'clicked', 'added' => 'added to cart from']],
    ];

    /** Prebuilt segments merchants start from. */
    public const TEMPLATES = [
        'new' => ['name' => 'New shoppers', 'description' => 'Shoppers who haven\'t ordered yet, including guests.', 'match' => 'all', 'rules' => [['field' => 'orders_count', 'op' => 'eq', 'value' => 0]]],
        'returning' => ['name' => 'Returning customers', 'description' => 'Customers with at least one order (signed in).', 'match' => 'all', 'rules' => [['field' => 'orders_count', 'op' => 'gte', 'value' => 1]]],
        'first-time' => ['name' => 'First-time buyers', 'description' => 'Customers with exactly one order: ready for a second.', 'match' => 'all', 'rules' => [['field' => 'orders_count', 'op' => 'eq', 'value' => 1]]],
        'high-aov' => ['name' => 'High-AOV customers', 'description' => 'Customers whose average order is $100 or more.', 'match' => 'all', 'rules' => [['field' => 'aov', 'op' => 'gte', 'value' => 100]]],
        'vip' => ['name' => 'VIP customers', 'description' => '3 or more orders, or $500 or more spent.', 'match' => 'any', 'rules' => [['field' => 'orders_count', 'op' => 'gte', 'value' => 3], ['field' => 'ltv', 'op' => 'gte', 'value' => 500]]],
        'product-purchasers' => ['name' => 'Product purchasers', 'description' => 'Customers who bought specific products. Choose the products.', 'match' => 'all', 'rules' => [['field' => 'product_purchased', 'op' => 'any', 'value' => []]]],
        'interactors' => ['name' => 'Widget interactors', 'description' => 'Shoppers who clicked a widget on this device. Choose the widget.', 'match' => 'all', 'rules' => [['field' => 'experience', 'op' => 'clicked', 'value' => '']]],
        'at-risk' => ['name' => 'At-risk customers', 'description' => 'Last order 60 to 180 days ago.', 'match' => 'all', 'rules' => [['field' => 'days_since_order', 'op' => 'gte', 'value' => 60], ['field' => 'days_since_order', 'op' => 'lte', 'value' => 180]]],
        'lapsed' => ['name' => 'Lapsed customers', 'description' => 'No order in the last 180 days.', 'match' => 'all', 'rules' => [['field' => 'days_since_order', 'op' => 'gte', 'value' => 180]]],
        'mobile' => ['name' => 'Mobile shoppers', 'description' => 'Browsing on a phone.', 'match' => 'all', 'rules' => [['field' => 'device', 'op' => 'is', 'value' => 'mobile']]],
    ];

    public const OUTCOMES = ['show' => 'Show the widget only to this audience', 'swap' => 'Show it in another template', 'hide' => 'Hide the widget'];

    /** Live-context conditions a rule can add to its segments. */
    public const CONDITIONS = ['device', 'cart_min', 'cart_max', 'utm_source', 'utm_campaign'];

    /**
     * Advanced personalization (its own plan feature): VIP / returning / lifecycle (order history),
     * product and device segments, and device or cart-value rule conditions.
     */
    public const ADVANCED_FIELDS = ['orders_count', 'ltv', 'aov', 'days_since_order', 'product_purchased', 'device'];

    public const ADVANCED_CONDITIONS = ['device', 'cart_min', 'cart_max'];

    public static function usesAdvanced(array $rules): bool
    {
        return collect($rules)->contains(fn ($r) => in_array($r['field'] ?? null, self::ADVANCED_FIELDS, true));
    }

    /**
     * Cleans a segment's rules. Returns [match, rules, errors keyed "rules.N"].
     *
     * @return array{0: string, 1: array, 2: array<string, string>}
     */
    public static function normalize(array $input): array
    {
        $match = ($input['match'] ?? 'all') === 'any' ? 'any' : 'all';
        $rules = [];
        $errors = [];
        foreach (array_values(array_filter((array) ($input['rules'] ?? []), 'is_array')) as $i => $r) {
            $def = self::FIELDS[$r['field'] ?? ''] ?? null;
            if (! $def) {
                continue;
            }
            $op = array_key_exists($r['op'] ?? '', $def['ops']) ? $r['op'] : array_key_first($def['ops']);
            $raw = $r['value'] ?? null;
            $value = match ($def['type']) {
                'number', 'money' => is_numeric($raw) ? max(0, (float) $raw) : null,
                'select' => isset($def['options'][$raw]) ? $raw : null,
                'products' => array_values(array_filter(array_map(fn ($p) => preg_match('/(\d+)$/', (string) (is_array($p) ? ($p['id'] ?? '') : $p), $m) ? ['id' => $m[1], 'title' => mb_substr(strip_tags((string) (is_array($p) ? ($p['title'] ?? '') : '')), 0, 120)] : null, is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: [])))),
                'experience' => preg_match('/^[A-Za-z0-9_\-]{1,64}$/', (string) $raw) ? (string) $raw : null,
                default => mb_substr(trim(strip_tags((string) $raw)), 0, 200),
            };
            if ($def['type'] === 'number' && $value !== null) {
                $value = (int) round($value);
            }
            if ($value === null || $value === '' || $value === []) {
                $errors["rules.{$i}"] = 'Fill in “'.$def['label'].'”.';
            }
            if ($r['field'] === 'country' && $value) {
                $codes = array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', $value)));
                if (array_filter($codes, fn ($c) => ! preg_match('/^[A-Z]{2}$/', $c))) {
                    $errors["rules.{$i}"] = 'Use two-letter country codes separated by commas.';
                }
                $value = implode(', ', $codes);
            }
            $rules[] = ['field' => $r['field'], 'op' => $op, 'value' => $value];
        }
        if (! $rules) {
            $errors['rules'] = 'Add at least one rule.';
        }

        return [$match, array_slice($rules, 0, 12), $errors];
    }

    /** Whether Shopify can count this segment's members (every rule is a customer search field). */
    public static function countable(Segment $segment): bool
    {
        return collect($segment->rules)->every(fn ($r) => isset(self::FIELDS[$r['field']]['search']));
    }

    /** Asks Shopify how many customers match, when every rule is a customer search field. */
    public function count(Store $store, Segment $segment): ?int
    {
        if (! self::countable($segment) || ! $segment->rules) {
            $segment->forceFill(['member_count' => null, 'counted_at' => now()])->save();

            return null;
        }
        $parts = collect($segment->rules)->map(function ($r) {
            $field = self::FIELDS[$r['field']]['search'];
            if ($field === 'tag') {
                $tag = '"'.str_replace(['"', '\\'], '', $r['value']).'"';

                return $r['op'] === 'not' ? "-tag:{$tag}" : "tag:{$tag}";
            }
            $op = ['gte' => '>=', 'lte' => '<=', 'eq' => ''][$r['op']] ?? '>=';

            return "{$field}:{$op}".(float) $r['value'];
        });
        try {
            $count = $this->api->graphql($store, 'query ($q: String!) { customersCount(query: $q) { count } }', ['q' => $parts->implode($segment->match === 'any' ? ' OR ' : ' AND ')])['customersCount']['count'] ?? null;
        } catch (Throwable $e) {
            report($e);
            $count = null;
        }
        $segment->forceFill(['member_count' => $count, 'counted_at' => now()])->save();

        return $count;
    }

    /**
     * Server-side match for workflows: the run's customer and order. Browsing rules (device,
     * experiences) aren't known on the server and don't match.
     */
    public static function matchesContext(Segment $segment, array $context): bool
    {
        $customer = $context['customer'] ?? [];
        $order = $context['order'] ?? [];
        $results = array_map(function ($r) use ($customer, $order) {
            $orders = (int) ($customer['orders_count'] ?? 0);
            $spent = (float) ($customer['total_spent'] ?? 0);
            $num = fn ($actual) => $actual !== null && match ($r['op']) { 'lte' => $actual <= $r['value'], 'eq' => abs($actual - $r['value']) < 0.0001, default => $actual >= $r['value'] };

            return match ($r['field']) {
                'orders_count' => $num($orders),
                'ltv' => $num($spent),
                'aov' => $orders ? $num($spent / $orders) : false,
                'days_since_order' => isset($order['created_at']) ? $num(floor((time() - strtotime($order['created_at'])) / 86400)) : false,
                'customer' => ($r['value'] === 'signed_in') === ! empty($customer['id']),
                'customer_tag' => (in_array(strtoupper($r['value']), array_map('strtoupper', $customer['tags'] ?? []), true)) === ($r['op'] !== 'not'),
                'product_purchased' => ((bool) array_intersect(array_column($r['value'], 'id'), $order['products'] ?? [])) === ($r['op'] !== 'not'),
                'country' => in_array(strtoupper($order['country'] ?? ''), array_map('trim', explode(',', $r['value'])), true) === ($r['op'] !== 'not'),
                default => false,
            };
        }, $segment->rules ?? []);

        return $results && ($segment->match === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true));
    }

    /** Experiences rules can personalize: the theme's own experiences, except Bundles and Progressive gifts. */
    public static function personalizable(Experience $experience): bool
    {
        if (! Registry::has($experience->type) || in_array($experience->type, ['bundles', 'progressive-gifts'], true)) {
            return false;
        }

        return ! in_array(Registry::type($experience->type)['surface'], Schema::CHECKOUT_SURFACES, true);
    }

    /**
     * Where a segment is used: experiences (targeting), rules, A/B tests (audience), workflows (conditions).
     *
     * @return array{experiences: array, rules: array, experiments: array, workflows: array}
     */
    public static function usage(Store $store, int $segmentId): array
    {
        $has = fn ($list) => in_array($segmentId, array_map('intval', (array) $list), true);

        return [
            'experiences' => Experience::with('publishedVersion')->where('store_id', $store->id)->where('status', '!=', 'archived')->get()
                ->filter(fn ($e) => $has($e->draft_config['targeting']['segments'] ?? []) || $has($e->publishedVersion?->config['targeting']['segments'] ?? []))->values()->all(),
            'rules' => PersonalizationRule::where('store_id', $store->id)->get()->filter(fn ($r) => $has($r->segments))->values()->all(),
            'experiments' => Experiment::where('store_id', $store->id)->whereIn('status', ['draft', 'running', 'paused'])->get()->filter(fn ($x) => $has($x->audience['segments'] ?? []))->values()->all(),
            'workflows' => Workflow::where('store_id', $store->id)->get()->filter(fn ($w) => self::usesSegment($w->draft ?? [], $segmentId))->values()->all(),
        ];
    }

    /** Whether a workflow definition has a "Customer segment" condition for this segment. */
    private static function usesSegment(array $node, int $id): bool
    {
        if (($node['field'] ?? null) === 'segment' && (int) ($node['value'] ?? 0) === $id) {
            return true;
        }
        foreach ($node as $child) {
            if (is_array($child) && self::usesSegment($child, $id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The storefront's personalization data: segments in use and enabled rules, highest priority
     * first. Only on plans with personalization; null when there's nothing to apply.
     */
    public static function payload(Store $store, array $liveHandles): ?array
    {
        if (! $store->planIncludes('personalization')) {
            return null;
        }
        $rules = PersonalizationRule::with('experience.publishedVersion')->where('store_id', $store->id)->where('enabled', true)->orderBy('position')->orderBy('id')->get()
            ->filter(fn ($r) => $r->experience && in_array($r->experience->handle, $liveHandles, true));
        $ids = $rules->flatMap(fn ($r) => $r->segments ?? []);
        $ids = $ids->merge(Experience::with('publishedVersion')->where('store_id', $store->id)->where('status', 'published')->get()->flatMap(fn ($e) => $e->publishedVersion?->config['targeting']['segments'] ?? []));
        $ids = $ids->merge(Experiment::where('store_id', $store->id)->where('status', 'running')->get()->flatMap(fn ($x) => $x->audience['segments'] ?? []));
        $segments = Segment::where('store_id', $store->id)->active()->whereIn('id', $ids->map(fn ($id) => (int) $id)->unique()->all())->get();
        // Without Advanced personalization, segments and rules that need it are left out: the
        // shoppers they'd pick out don't match, so a targeted offer never reaches everyone.
        if (! $store->planIncludes('personalization_advanced')) {
            $segments = $segments->reject(fn ($s) => self::usesAdvanced($s->rules ?? []))->values();
            $rules = $rules->reject(fn ($r) => array_intersect(array_keys($r->conditions ?? []), self::ADVANCED_CONDITIONS) !== []);
        }
        if ($rules->isEmpty() && $segments->isEmpty()) {
            return null;
        }

        return [
            'segments' => (object) $segments->mapWithKeys(fn ($s) => [(string) $s->id => [
                'match' => $s->match,
                'rules' => array_map(fn ($r) => $r['field'] === 'product_purchased' ? ['field' => $r['field'], 'op' => $r['op'], 'value' => array_column($r['value'], 'id')] : $r, $s->rules),
            ]])->all(),
            'rules' => $rules->map(fn ($r) => array_filter([
                'experience' => $r->experience->handle,
                'segments' => array_map('strval', $r->segments ?? []),
                'when' => $r->conditions ? (object) $r->conditions : null,
                'outcome' => $r->outcome,
                'template' => $r->outcome === 'swap' ? $r->template_key : null,
                'style' => $r->outcome === 'swap' ? (Registry::template($r->experience->type, (string) $r->template_key)['style'] ?? null) : null,
            ], fn ($v) => $v !== null && $v !== []))->values()->all(),
        ];
    }

    /** Rules that target the same experience: the higher one wins when both match. */
    public static function conflicts(iterable $rules): array
    {
        $out = [];
        foreach (collect($rules)->where('enabled', true)->groupBy('experience_id') as $group) {
            if ($group->count() > 1) {
                $out[] = $group->pluck('name')->all();
            }
        }

        return $out;
    }
}
