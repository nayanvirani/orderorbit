<?php

namespace App\Services\Integrations;

use App\Models\Audiences\Segment;
use App\Models\Integrations\StoreIntegration;
use App\Models\Store;
use App\Services\Audiences\Audiences;
use App\Services\Shopify\AdminApi;
use App\Support\Features;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klaviyo (G31): Growvia events become Klaviyo metrics for flows, and chosen Growvia segments
 * sync daily to Klaviyo lists ("Growvia: <segment>"), with only customers who accept email
 * marketing. Events go only for signed-in customers, whose email Growvia reads from Shopify on the
 * server (the web pixel never sends emails).
 */
class Klaviyo
{
    public const EVENTS = [
        'offer_viewed' => 'Offer viewed',
        'offer_added' => 'Offer added to cart',
        'bundle_bought' => 'Bundle bought',
        'reward_unlocked' => 'Reward unlocked',
        'survey_answered' => 'Survey answered',
        'workflow_email_sent' => 'Workflow email sent',
    ];

    private const API = 'https://a.klaviyo.com/api';

    private const REVISION = '2024-10-15';

    /** At most this many customers are synced to one list. */
    public const MAX_MEMBERS = 10000;

    public function __construct(private readonly AdminApi $shopify) {}

    public static function for(Store $store): ?StoreIntegration
    {
        return Features::enabled('integrations', $store) && $store->planIncludes('klaviyo_ga4')
            ? StoreIntegration::where('store_id', $store->id)->where('provider', 'klaviyo')->first() : null;
    }

    private function http(string $key): PendingRequest
    {
        return Http::timeout(15)->withHeaders(['Authorization' => 'Klaviyo-API-Key '.$key, 'revision' => self::REVISION, 'Accept' => 'application/vnd.api+json'])->asJson();
    }

    /** Checks the private key against Klaviyo, then saves it. Returns an error, or null. */
    public function connect(Store $store, string $key): ?string
    {
        $key = trim($key);
        if (! preg_match('/^pk_[A-Za-z0-9]{20,80}$/', $key)) {
            return 'A Klaviyo private API key starts with pk_ (Klaviyo › Settings › API keys › Create private API key).';
        }
        if ($error = $this->check($key)) {
            return $error;
        }
        $i = StoreIntegration::updateOrCreate(['store_id' => $store->id, 'provider' => 'klaviyo'], [
            'credentials' => ['api_key' => $key], 'settings' => ['events' => array_fill_keys(array_keys(self::EVENTS), true), 'segments' => [], 'lists' => []],
            'status' => 'connected', 'last_error' => null,
        ]);
        $i->note('Connected to Klaviyo.');

        return null;
    }

    private function check(string $key): ?string
    {
        try {
            $r = $this->http($key)->get(self::API.'/lists/', ['page[size]' => 1]);
        } catch (Throwable) {
            return 'Couldn\'t reach Klaviyo. Try again in a minute.';
        }

        return match (true) {
            $r->successful() => null,
            in_array($r->status(), [401, 403], true) => 'Klaviyo didn\'t accept the key. It needs access to Lists, Profiles and Events.',
            default => 'Klaviyo answered with an error ('.$r->status().').',
        };
    }

    /** "Send test": a test event for the store owner's email. */
    public function test(StoreIntegration $i, string $email): ?string
    {
        $error = $this->event($i, $email, 'offer_viewed', ['offer' => 'Test from Growvia'], 'test-'.uniqid()) ? null : 'Klaviyo didn\'t accept the test event.';
        $i->note($error ?? "Test event sent for {$email}. It shows in Klaviyo › Analytics › Metrics within a minute.", $error === null);

        return $error;
    }

    /** One event for a profile (by email). Returns whether Klaviyo accepted it. */
    public function event(StoreIntegration $i, string $email, string $metric, array $properties, string $uniqueId): bool
    {
        if (! (((array) $i->setting('events', []))[$metric] ?? false) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            $r = $this->http($i->credentials['api_key'])->post(self::API.'/events/', ['data' => ['type' => 'event', 'attributes' => [
                'properties' => $properties + ['source' => 'Growvia'],
                'metric' => ['data' => ['type' => 'metric', 'attributes' => ['name' => 'Growvia: '.self::EVENTS[$metric]]]],
                'profile' => ['data' => ['type' => 'profile', 'attributes' => ['email' => $email]]],
                'time' => now()->toIso8601String(), 'unique_id' => mb_substr($uniqueId, 0, 120),
            ]]]);
            $ok = $r->successful();
        } catch (Throwable) {
            $ok = false;
        }
        $today = now()->toDateString();
        $sent = (array) $i->setting('sent', []);
        $first = ! isset($sent[$today]);
        $i->forceFill(['settings' => array_merge((array) $i->settings, ['sent' => [$today => ($sent[$today] ?? 0) + ($ok ? 1 : 0)]]), 'last_sync_at' => now()])->save();
        if (! $ok) {
            $i->note('Klaviyo didn\'t accept an event. Check the API key.', false);
        } elseif ($first) {
            $i->note('Sending events to Klaviyo today.');
        }

        return $ok;
    }

    /** A signed-in customer's email, from Shopify (cached a day). */
    public function email(Store $store, string $customerId): ?string
    {
        if (! ctype_digit($customerId)) {
            return null;
        }

        return Cache::remember("klaviyo-email:{$store->id}:{$customerId}", 86400, function () use ($store, $customerId) {
            try {
                return $this->shopify->graphql($store, 'query ($id: ID!) { customer(id: $id) { email } }', ['id' => "gid://shopify/Customer/{$customerId}"])['customer']['email'] ?? null;
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        });
    }

    /** Daily: each chosen segment's customers who accept email marketing, into its Klaviyo list. */
    public function syncLists(Store $store, StoreIntegration $i): void
    {
        $lists = (array) $i->setting('lists', []);
        foreach (Segment::where('store_id', $store->id)->whereIn('id', (array) $i->setting('segments', []))->get() as $segment) {
            if (! Audiences::countable($segment) || ! $segment->rules) {
                continue;
            }
            try {
                $listId = $lists[$segment->id] ?? null;
                if (! $listId || ! $this->listExists($i, $listId)) {
                    $listId = $this->http($i->credentials['api_key'])->post(self::API.'/lists/', ['data' => ['type' => 'list', 'attributes' => ['name' => 'Growvia: '.$segment->name]]])->throw()->json('data.id');
                    $lists[$segment->id] = $listId;
                }
                $want = $this->members($store, Audiences::searchQuery($segment));
                $have = $this->listMembers($i, $listId);
                $add = array_values(array_diff($want, array_keys($have)));
                $remove = array_values(array_intersect_key($have, array_flip(array_diff(array_keys($have), $want))));
                foreach (array_chunk($add, 5000) as $chunk) {
                    $this->http($i->credentials['api_key'])->post(self::API.'/profile-bulk-import-jobs/', ['data' => ['type' => 'profile-bulk-import-job',
                        'attributes' => ['profiles' => ['data' => array_map(fn ($e) => ['type' => 'profile', 'attributes' => ['email' => $e]], $chunk)]],
                        'relationships' => ['lists' => ['data' => [['type' => 'list', 'id' => $listId]]]]]])->throw();
                }
                foreach (array_chunk($remove, 1000) as $chunk) {
                    $this->http($i->credentials['api_key'])->delete(self::API."/lists/{$listId}/relationships/profiles/", ['data' => array_map(fn ($id) => ['type' => 'profile', 'id' => $id], $chunk)])->throw();
                }
                $i->note("{$segment->name}: ".count($want).' in the list ('.count($add).' added, '.count($remove).' removed).');
            } catch (Throwable $e) {
                report($e);
                $i->note("{$segment->name}: the list couldn't be synced.", false);
            }
        }
        $i->forceFill(['settings' => array_merge((array) $i->settings, ['lists' => $lists]), 'last_sync_at' => now()])->save();
    }

    /** Emails of customers matching the search who accept email marketing. */
    public function members(Store $store, string $query): array
    {
        $emails = [];
        $after = null;
        do {
            $page = $this->shopify->graphql($store, 'query ($q: String!, $after: String) { customers(first: 250, after: $after, query: $q) { pageInfo { hasNextPage endCursor }
                nodes { email emailMarketingConsent { marketingState } } } }', ['q' => $query, 'after' => $after])['customers'] ?? [];
            foreach ((array) ($page['nodes'] ?? []) as $c) {
                if (! empty($c['email']) && ($c['emailMarketingConsent']['marketingState'] ?? null) === 'SUBSCRIBED') {
                    $emails[] = strtolower($c['email']);
                }
            }
            $after = ($page['pageInfo']['hasNextPage'] ?? false) ? $page['pageInfo']['endCursor'] : null;
        } while ($after && count($emails) < self::MAX_MEMBERS);

        return array_values(array_unique(array_slice($emails, 0, self::MAX_MEMBERS)));
    }

    /** email => Klaviyo profile id, for the list's current members. */
    private function listMembers(StoreIntegration $i, string $listId): array
    {
        $out = [];
        $url = self::API."/lists/{$listId}/profiles/?fields[profile]=email&page[size]=100";
        for ($n = 0; $url && $n < 200; $n++) {
            $r = $this->http($i->credentials['api_key'])->get($url)->throw();
            foreach ((array) $r->json('data', []) as $p) {
                if (! empty($p['attributes']['email'])) {
                    $out[strtolower($p['attributes']['email'])] = $p['id'];
                }
            }
            $url = $r->json('links.next');
        }

        return $out;
    }

    private function listExists(StoreIntegration $i, string $listId): bool
    {
        return $this->http($i->credentials['api_key'])->get(self::API."/lists/{$listId}/")->successful();
    }
}
