<?php

namespace App\Services\Insights;

use App\Models\Store;
use App\Services\Shopify\AdminApi;
use App\Support\OptionalAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Store sessions and conversion rate from Shopify's reports (ShopifyQL, optional read_reports
 * access), so insights cover the whole store, not only visitors who saw a Growvia offer. Cached
 * for an hour. Anything unexpected in Shopify's answer shows nothing rather than a wrong number.
 */
class StoreTraffic
{
    public function __construct(private readonly AdminApi $api) {}

    /** [sessions, conversion rate in %] for the dates, or null without access or data. */
    public function get(Store $store, Carbon $from, Carbon $to): ?array
    {
        if (! OptionalAccess::granted($store, 'read_reports')) {
            return null;
        }

        return Cache::remember("traffic:{$store->id}:{$from->toDateString()}:{$to->toDateString()}", 3600, function () use ($store, $from, $to) {
            try {
                $q = "FROM sessions SHOW sessions, conversion_rate SINCE {$from->toDateString()} UNTIL {$to->toDateString()}";
                $data = $this->api->graphql($store, 'query ($q: String!) { shopifyqlQuery(query: $q) { tableData { columns { name } rows } parseErrors } }', ['q' => $q])['shopifyqlQuery'] ?? null;

                return self::parse($data);
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        });
    }

    /** Shopify's table to [sessions, conversion %]; null when it isn't what's expected. */
    public static function parse(?array $data): ?array
    {
        if (! $data || ! empty($data['parseErrors']) || empty($data['tableData']['rows'])) {
            return null;
        }
        $columns = array_column((array) ($data['tableData']['columns'] ?? []), 'name');
        $row = $data['tableData']['rows'][0];
        $row = is_string($row) ? json_decode($row, true) : $row;
        if (! is_array($row)) {
            return null;
        }
        // Rows come keyed by column name, or as a list in column order.
        $value = fn (string $name) => array_key_exists($name, $row) ? $row[$name] : (($i = array_search($name, $columns, true)) !== false ? ($row[$i] ?? null) : null);
        $sessions = $value('sessions');
        $rate = $value('conversion_rate');
        if (! is_numeric($sessions)) {
            return null;
        }
        $rate = is_numeric($rate) ? (float) $rate : null;

        return ['sessions' => (int) $sessions, 'conversion' => $rate === null ? null : round($rate <= 1 ? $rate * 100 : $rate, 2)];
    }
}
