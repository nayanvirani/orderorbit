<?php

namespace App\Services\Protect;

use App\Models\Profit\ProductCost;
use App\Models\Protect\MonitoredUrl;
use App\Models\Protect\ProtectAlert;
use App\Models\Protect\UrlCheck;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * URL monitor (G10): the home page, best sellers, a few collections and the merchant's own pages,
 * checked every hour, 15 or 5 minutes by plan. Two failed checks in a row raise a "Page down"
 * alert; the first good check closes it. A password-protected store isn't counted as down.
 */
class UrlMonitor
{
    /** Slower than this (ms) is "slow". */
    public const SLOW_MS = 3000;

    public const KINDS = ['home' => 'Home', 'best_seller' => 'Best seller', 'collection' => 'Collection', 'custom' => 'Custom'];

    public function __construct(private readonly AdminApi $api, private readonly Protect $protect) {}

    /** How many pages the plan allows (null = no limit). */
    public function limit(Store $store): ?int
    {
        return $store->planIncludes('url_monitor') ? $store->planLimit('url_monitors') : 0;
    }

    /** Minutes between checks: more pages on the plan, more often (Growth hourly, Scale 15, Premium 5). */
    public function interval(Store $store): int
    {
        $limit = $this->limit($store);

        return match (true) {
            $limit === null || $limit >= 100 => 5,
            $limit >= 50 => 15,
            default => 60,
        };
    }

    public function active(Store $store)
    {
        return MonitoredUrl::where('store_id', $store->id)->where('removed', false)->orderByRaw("CASE kind WHEN 'home' THEN 0 WHEN 'best_seller' THEN 1 WHEN 'collection' THEN 2 ELSE 3 END")->orderBy('id')->get();
    }

    /** Room left on the plan. */
    public function room(Store $store): int
    {
        $limit = $this->limit($store);

        return $limit === null ? PHP_INT_MAX : max(0, $limit - MonitoredUrl::where('store_id', $store->id)->where('removed', false)->count());
    }

    /**
     * Adds the pages worth watching: home, the best sellers' product pages (from Profit's synced
     * orders, when there are any) and the 3 most recently updated collections. Only pages that load
     * now are added, so an unpublished one never shows as down. Pages taken off stay off.
     */
    public function discover(Store $store): int
    {
        $productIds = [];
        $variants = ProductCost::where('store_id', $store->id)->get()->keyBy(fn ($v) => (int) $v->variant_id);
        foreach ($this->protect->bestSellers($this->protect->sales($store)) as $variantId) {
            if ($p = $variants[$variantId]->product_id ?? null) {
                $productIds[(int) $p] = 'gid://shopify/Product/'.$p;
            }
        }
        $data = $this->api->graphql($store, 'query ($ids: [ID!]!) { shop { primaryDomain { url } }
            collections(first: 3, sortKey: UPDATED_AT, reverse: true) { nodes { title handle } }
            nodes(ids: $ids) { ... on Product { title onlineStoreUrl } } }', ['ids' => array_values(array_slice($productIds, 0, 5, true))]);
        $base = rtrim((string) ($data['shop']['primaryDomain']['url'] ?? 'https://'.$store->shop_domain), '/');

        $candidates = [$base.'/' => ['home', 'Home page']];
        foreach ((array) ($data['nodes'] ?? []) as $p) {
            if (! empty($p['onlineStoreUrl'])) {
                $candidates[$base.(parse_url($p['onlineStoreUrl'], PHP_URL_PATH) ?: '/')] = ['best_seller', $p['title'] ?? null];
            }
        }
        foreach ((array) ($data['collections']['nodes'] ?? []) as $c) {
            if (! empty($c['handle'])) {
                $candidates[$base.'/collections/'.$c['handle']] = ['collection', $c['title'] ?? null];
            }
        }

        $known = MonitoredUrl::where('store_id', $store->id)->pluck('url')->all();
        $new = array_diff_key($candidates, array_flip($known));
        $new = array_slice($new, 0, $this->room($store), true);
        if (! $new) {
            return 0;
        }
        $added = 0;
        foreach ($this->probe(array_keys($new)) as $url => $r) {
            if ($r['ok'] || $r['locked']) {
                MonitoredUrl::create(['store_id' => $store->id, 'url' => $url, 'kind' => $new[$url][0], 'label' => $new[$url][1]]);
                $added++;
            }
        }

        return $added;
    }

    /**
     * A page the merchant adds: a path ("/pages/size-guide") or a full URL on the store's own
     * domain. Returns an error message, or null when it was added.
     */
    public function add(Store $store, string $input): ?string
    {
        $input = trim($input);
        $home = MonitoredUrl::where('store_id', $store->id)->where('kind', 'home')->value('url');
        $base = rtrim($home ?: 'https://'.$store->shop_domain.'/', '/');
        if (str_starts_with($input, '/')) {
            $url = $base.$input;
        } elseif (preg_match('#^https?://#i', $input)) {
            $host = strtolower((string) parse_url($input, PHP_URL_HOST));
            if (! in_array($host, [strtolower((string) parse_url($base, PHP_URL_HOST)), strtolower($store->shop_domain)], true)) {
                return 'Add a page on your own store ('.parse_url($base, PHP_URL_HOST).').';
            }
            $url = $input;
        } else {
            return 'Start with / (like /pages/size-guide) or paste the full address.';
        }
        if (mb_strlen($url) > 500) {
            return 'That address is too long.';
        }
        $existing = MonitoredUrl::where('store_id', $store->id)->where('url', $url)->first();
        if ($existing && ! $existing->removed) {
            return 'That page is already on the list.';
        }
        if ($this->room($store) <= 0) {
            return 'Your plan\'s page limit is reached. Remove a page or upgrade to add more.';
        }
        $row = $existing ?? new MonitoredUrl(['store_id' => $store->id, 'url' => $url, 'kind' => 'custom']);
        $row->forceFill(['removed' => false, 'status' => 'new', 'fails' => 0, 'down_since' => null])->save();
        $this->check($store, collect([$row]));

        return null;
    }

    /** Takes a page off: found pages aren't added back; the merchant's own are deleted. */
    public function remove(Store $store, MonitoredUrl $url): void
    {
        $url->kind === 'custom' ? $url->delete() : $url->forceFill(['removed' => true, 'status' => 'new', 'fails' => 0, 'down_since' => null])->save();
        $this->closeAlert($store, $url->id);
    }

    /** Checks the pages that are due (or the given ones), records each check and opens or closes alerts. */
    public function check(Store $store, $urls = null): array
    {
        $urls ??= MonitoredUrl::where('store_id', $store->id)->where('removed', false)
            ->where(fn ($q) => $q->whereNull('checked_at')->orWhere('checked_at', '<=', now()->subMinutes($this->interval($store))->addSeconds(30)))->get();
        if ($urls->isEmpty()) {
            return ['checked' => 0, 'down' => 0];
        }
        $results = $this->probe($urls->pluck('url')->all());
        $now = now();
        $alerts = in_array('page_down', app(Alerts::class)->activeRules($store), true);
        $opened = false;
        foreach ($urls as $u) {
            $r = $results[$u->url] ?? null;
            if (! $r || $r['skip']) {
                continue; // rate limited: neither up nor down
            }
            if ($r['locked']) {
                $u->forceFill(['status' => 'locked', 'last_code' => $r['code'], 'last_ms' => $r['ms'], 'last_error' => null, 'fails' => 0, 'down_since' => null, 'checked_at' => $now])->save();
                $this->closeAlert($store, $u->id);
                continue;
            }
            UrlCheck::create(['monitored_url_id' => $u->id, 'checked_at' => $now, 'ok' => $r['ok'], 'slow' => $r['ok'] && $r['ms'] > self::SLOW_MS, 'code' => $r['code'], 'ms' => $r['ms']]);
            if ($r['ok']) {
                $u->forceFill(['status' => $r['ms'] > self::SLOW_MS ? 'slow' : 'up', 'fails' => 0, 'down_since' => null, 'last_error' => null]);
                $this->closeAlert($store, $u->id);
            } else {
                $fails = $u->fails + 1;
                $u->forceFill(['fails' => $fails, 'last_error' => $r['error'], 'down_since' => $u->down_since ?? $now]);
                // Two failed checks in a row before it counts as down (one blip isn't an outage).
                if ($fails >= 2) {
                    $u->status = 'down';
                    if ($alerts && $this->openAlert($store, $u)) {
                        $opened = true;
                    }
                }
            }
            $u->forceFill(['last_code' => $r['code'], 'last_ms' => $r['ms'], 'checked_at' => $now])->save();
        }
        if ($opened) {
            app(Alerts::class)->email($store);
        }

        return ['checked' => $urls->count(), 'down' => $urls->where('status', 'down')->count()];
    }

    /**
     * Loads each page once (10 at a time, no redirects followed): [ok, locked, skip, code, ms, error]
     * per URL. A redirect to /password means the store is password protected.
     */
    public function probe(array $urls): array
    {
        $out = [];
        foreach (array_chunk($urls, 10) as $chunk) {
            $started = microtime(true);
            try {
                $responses = Http::pool(fn (Pool $pool) => array_map(fn ($url) => $pool->as($url)->withoutRedirecting()->connectTimeout(5)->timeout(15)
                    ->withHeaders(['User-Agent' => 'Growvia URL monitor (+'.rtrim((string) config('app.url'), '/').')', 'Accept' => 'text/html'])->get($url), $chunk));
            } catch (Throwable $e) {
                report($e);
                continue;
            }
            $elapsed = (int) round((microtime(true) - $started) * 1000);
            foreach ($chunk as $url) {
                $r = $responses[$url] ?? null;
                if (! $r instanceof Response) {
                    $out[$url] = ['ok' => false, 'locked' => false, 'skip' => false, 'code' => null, 'ms' => null, 'error' => $this->errorText($r)];

                    continue;
                }
                $code = $r->status();
                $ms = isset($r->handlerStats()['total_time']) ? (int) round($r->handlerStats()['total_time'] * 1000) : $elapsed;
                $location = (string) $r->header('Location');
                $locked = $r->redirect() && str_contains(parse_url($location, PHP_URL_PATH) ?? '', '/password');
                $out[$url] = ['ok' => ($code >= 200 && $code < 400) && ! $locked, 'locked' => $locked, 'skip' => $code === 429, 'code' => $code, 'ms' => $ms,
                    'error' => $code >= 400 ? $code.' '.($this->reason($code)) : null];
            }
        }

        return $out;
    }

    /** "Page down" alert for a page, unless it's already open (or muted). Returns whether it opened now. */
    private function openAlert(Store $store, MonitoredUrl $u): bool
    {
        $alert = ProtectAlert::firstOrNew(['store_id' => $store->id, 'rule' => 'page_down', 'subject' => 'url:'.$u->id]);
        $new = ! $alert->exists || $alert->status === 'closed';
        $path = $u->path();
        $alert->fill([
            'severity' => in_array($u->kind, ['home', 'best_seller'], true) ? 'urgent' : 'warning',
            'title' => "{$path} is down", 'what' => 'It '.($u->last_error ? 'returns "'.$u->last_error.'"' : 'doesn\'t load').' since '.($u->down_since ?? now())->format('D j M, H:i').' (two checks in a row).',
            'cost' => 0, 'cost_text' => 'Shoppers who open it see an error', 'fix' => 'Check the page in Shopify: deleted, unpublished or renamed? Fix the link or add a redirect.',
            'cta' => 'Open URL monitor', 'cta_url' => route('app.protect.urls', [], false), 'last_seen_at' => now(),
        ]);
        if ($new) {
            $alert->fill(['status' => 'open', 'opened_at' => now(), 'closed_at' => null, 'emailed_at' => null]);
        }
        $alert->save();

        return $new;
    }

    private function closeAlert(Store $store, int $urlId): void
    {
        ProtectAlert::where('store_id', $store->id)->where('rule', 'page_down')->where('subject', 'url:'.$urlId)->whereIn('status', ['open', 'snoozed'])
            ->update(['status' => 'closed', 'closed_at' => now()]);
    }

    private function errorText($e): string
    {
        $m = $e instanceof Throwable ? $e->getMessage() : '';

        return match (true) {
            str_contains($m, 'timed out') || str_contains($m, 'Timeout') => 'No answer in 15 seconds',
            str_contains($m, 'resolve') => 'Domain not found',
            default => 'Couldn\'t connect',
        };
    }

    private function reason(int $code): string
    {
        return [400 => 'Bad request', 401 => 'Not allowed', 403 => 'Forbidden', 404 => 'Not found', 410 => 'Gone', 500 => 'Server error', 502 => 'Bad gateway', 503 => 'Unavailable', 504 => 'Timed out'][$code] ?? 'Error';
    }

    /**
     * Rows for the page: status, load time, the last 24 hours (one bar an hour: down, slow, up or
     * no check) and uptime over 30 days.
     */
    public function rows(Store $store): array
    {
        $urls = $this->active($store);
        $checks = UrlCheck::whereIn('monitored_url_id', $urls->pluck('id'))->where('checked_at', '>=', now()->subDays(30))->get()->groupBy('monitored_url_id');
        $hourStart = now()->startOfHour()->subHours(23);

        return $urls->map(function (MonitoredUrl $u) use ($checks, $hourStart) {
            $mine = $checks[$u->id] ?? collect();
            $strip = [];
            for ($h = 0; $h < 24; $h++) {
                $from = $hourStart->copy()->addHours($h);
                $in = $mine->filter(fn ($c) => $c->checked_at->gte($from) && $c->checked_at->lt($from->copy()->addHour()));
                $strip[] = $in->isEmpty() ? 'none' : ($in->contains(fn ($c) => ! $c->ok) ? 'down' : ($in->contains('slow', true) ? 'slow' : 'up'));
            }

            return [
                'id' => $u->id, 'url' => $u->url, 'path' => $u->path(), 'label' => $u->label, 'kind' => $u->kind, 'type' => self::KINDS[$u->kind] ?? 'Custom',
                // One failed check shows as "failing"; a second makes it "down".
                'status' => $u->status === 'locked' ? 'locked' : ($u->fails >= 2 ? 'down' : ($u->fails === 1 ? 'failing' : $u->status)),
                'ms' => $u->last_ms, 'error' => $u->last_error, 'down_since' => $u->down_since, 'checked_at' => $u->checked_at,
                'strip' => $strip, 'uptime' => $mine->count() ? round($mine->where('ok', true)->count() / $mine->count() * 100, 1) : null,
            ];
        })->all();
    }

    /** Checks older than 30 days are deleted. */
    public static function prune(): int
    {
        return UrlCheck::where('checked_at', '<', now()->subDays(31))->delete();
    }
}
