<?php

namespace App\Services\Integrations;

use App\Models\Integrations\StoreIntegration;
use App\Models\Store;
use App\Support\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Review apps, step 2 (G30): published reviews from Judge.me (API token) or Yotpo (app key and
 * secret), synced daily. A review hidden or deleted in the app disappears on the next sync, and
 * nothing can be edited in Growvia. Loox limits its API by plan, so Loox stores keep the star
 * ratings from step 1.
 */
class ReviewSync
{
    public const SOURCES = ['judgeme' => 'Judge.me', 'yotpo' => 'Yotpo'];

    public const OPTIONS = ['four_up' => 'Only show 4★ and up', 'photos_only' => 'Only show reviews with photos'];

    /** Reviews kept per store (newest first). */
    public const MAX = 5000;

    public static function connected(Store $store): array
    {
        if (! Features::enabled('review_apps', $store) || ! Features::enabled('integrations', $store) || ! $store->planIncludes('review_apps')) {
            return [];
        }

        return StoreIntegration::where('store_id', $store->id)->whereIn('provider', array_keys(self::SOURCES))->get()->all();
    }

    /** Checks the keys by reading the first page, saves them and syncs. Returns an error, or null. */
    public function connect(Store $store, string $source, array $keys): ?string
    {
        $keys = array_map('trim', $keys);
        try {
            $this->page($store, $source, $keys, 1);
        } catch (Throwable $e) {
            return $e instanceof RuntimeException ? $e->getMessage() : 'Couldn\'t reach '.self::SOURCES[$source].'. Try again in a minute.';
        }
        $i = StoreIntegration::updateOrCreate(['store_id' => $store->id, 'provider' => $source], [
            'credentials' => $keys, 'settings' => ['events' => ['four_up' => false, 'photos_only' => false]], 'status' => 'connected', 'last_error' => null,
        ]);
        $i->note('Connected to '.self::SOURCES[$source].'.');
        $this->sync($store, $i);

        return null;
    }

    /** Reads every published review and replaces the stored ones for this source. */
    public function sync(Store $store, StoreIntegration $i): int
    {
        $source = $i->provider;
        $seen = [];
        try {
            for ($page = 1; $page <= 100 && count($seen) < self::MAX; $page++) {
                $rows = $this->page($store, $source, (array) $i->credentials, $page);
                if (! $rows) {
                    break;
                }
                foreach ($rows as $r) {
                    $seen[] = $r['external_id'];
                    DB::table('store_reviews')->updateOrInsert(['store_id' => $store->id, 'source' => $source, 'external_id' => $r['external_id']],
                        $r + ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        } catch (Throwable $e) {
            report($e);
            $i->note(self::SOURCES[$source].': reviews couldn\'t be synced. '.($e instanceof RuntimeException ? $e->getMessage() : ''), false);

            return 0;
        }
        // Hidden, unpublished or deleted in the app: gone here too.
        DB::table('store_reviews')->where('store_id', $store->id)->where('source', $source)->whereNotIn('external_id', $seen ?: ['-'])->delete();
        $i->forceFill(['last_sync_at' => now()])->save();
        $i->note('Synced '.count($seen).' published reviews.');

        return count($seen);
    }

    /** One page of published reviews, normalized. Empty when there are no more. */
    public function page(Store $store, string $source, array $keys, int $page): array
    {
        return $source === 'judgeme' ? $this->judgeme($store, $keys, $page) : $this->yotpo($keys, $page);
    }

    private function judgeme(Store $store, array $keys, int $page): array
    {
        $r = Http::timeout(20)->acceptJson()->get('https://judge.me/api/v1/reviews', ['shop_domain' => $store->shop_domain, 'api_token' => $keys['api_token'] ?? '', 'per_page' => 100, 'page' => $page]);
        if (in_array($r->status(), [401, 403], true)) {
            throw new RuntimeException('Judge.me didn\'t accept the API token (Judge.me › Settings › Integrations › Developers › Private API token).');
        }
        $r->throw();

        return collect((array) $r->json('reviews', []))
            ->filter(fn ($v) => ($v['published'] ?? true) && ! ($v['hidden'] ?? false) && ($v['curated'] ?? 'ok') !== 'spam')
            ->map(fn ($v) => self::normal((string) $v['id'], $v['product_external_id'] ?? null, $v['rating'] ?? 0, $v['title'] ?? null, $v['body'] ?? null,
                $v['reviewer']['name'] ?? null, $v['pictures'][0]['urls']['original'] ?? $v['pictures'][0]['urls']['huge'] ?? null, $v['created_at'] ?? null))
            ->filter()->values()->all();
    }

    private function yotpo(array $keys, int $page): array
    {
        $appKey = $keys['app_key'] ?? '';
        $token = cache()->remember('yotpo-token:'.sha1($appKey.($keys['secret'] ?? '')), 3000, function () use ($appKey, $keys) {
            $r = Http::timeout(20)->post('https://api.yotpo.com/oauth/token', ['client_id' => $appKey, 'client_secret' => $keys['secret'] ?? '', 'grant_type' => 'client_credentials']);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new RuntimeException('Yotpo didn\'t accept the app key and secret (Yotpo › Settings › Store › API credentials).');
            }

            return $r->json('access_token');
        });
        $r = Http::timeout(20)->acceptJson()->get("https://api.yotpo.com/v1/apps/{$appKey}/reviews", ['utoken' => $token, 'count' => 100, 'page' => $page]);
        $r->throw();

        return collect((array) $r->json('reviews', []))
            ->filter(fn ($v) => ! ($v['deleted'] ?? false) && ($v['published'] ?? true) !== false)
            ->map(fn ($v) => self::normal((string) $v['id'], $v['sku'] ?? null, $v['score'] ?? 0, $v['title'] ?? null, $v['content'] ?? null,
                $v['name'] ?? $v['user_name'] ?? null, $v['images_data'][0]['image_url'] ?? null, $v['created_at'] ?? null))
            ->filter()->values()->all();
    }

    /** A review as stored: first name only, plain text, a photo only over https. */
    private static function normal(string $id, mixed $product, mixed $rating, ?string $title, ?string $body, ?string $author, ?string $photo, ?string $at): ?array
    {
        $rating = (int) round((float) $rating);
        if ($rating < 1 || $rating > 5) {
            return null;
        }
        $clean = fn (?string $t, int $max) => $t === null ? null : (mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($t)))), 0, $max) ?: null);

        return [
            'external_id' => mb_substr($id, 0, 64), 'product_id' => is_numeric($product) ? (int) $product : null, 'rating' => $rating,
            'title' => $clean($title, 200), 'body' => $clean($body, 1000),
            'author' => $author ? mb_substr(explode(' ', trim($author))[0], 0, 60) : null,
            'photo' => is_string($photo) && str_starts_with($photo, 'https://') ? mb_substr($photo, 0, 500) : null,
            'reviewed_at' => $at ? \Illuminate\Support\Carbon::parse($at) : null,
        ];
    }

    /**
     * For the storefront: product id => up to 4 reviews, and the store's best 8 for other pages,
     * after the merchant's filters. [rating, title, text, first name, date, photo, source name]
     */
    public static function forStorefront(Store $store): ?array
    {
        $apps = self::connected($store);
        if (! $apps) {
            return null;
        }
        $fourUp = collect($apps)->contains(fn ($i) => (bool) (((array) $i->setting('events', []))['four_up'] ?? false));
        $photos = collect($apps)->contains(fn ($i) => (bool) (((array) $i->setting('events', []))['photos_only'] ?? false));
        $rows = DB::table('store_reviews')->where('store_id', $store->id)->whereIn('source', array_map(fn ($i) => $i->provider, $apps))
            ->when($fourUp, fn ($q) => $q->where('rating', '>=', 4))->when($photos, fn ($q) => $q->whereNotNull('photo'))
            ->orderByDesc('reviewed_at')->limit(3000)->get();
        $row = fn ($r) => [$r->rating, $r->title, $r->body ? mb_substr($r->body, 0, 240) : null, $r->author, $r->reviewed_at ? substr((string) $r->reviewed_at, 0, 10) : null, $r->photo, self::SOURCES[$r->source]];
        $byProduct = $rows->whereNotNull('product_id')->groupBy('product_id')->sortByDesc(fn ($g) => $g->count())->take(100)
            ->map(fn ($g) => $g->take(4)->map($row)->values()->all())->all();

        return ['p' => $byProduct, 's' => $rows->sortByDesc('rating')->take(8)->map($row)->values()->all()];
    }
}
