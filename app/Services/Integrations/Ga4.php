<?php

namespace App\Services\Integrations;

use App\Models\Integrations\StoreIntegration;
use App\Models\Store;
use App\Support\Features;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Analytics 4 (G32): Growvia events next to the store's own in GA4, through the Measurement
 * Protocol, with the shopper's GA client id (read by the web pixel, which only runs with analytics
 * consent). No names or emails are sent.
 */
class Ga4
{
    public const EVENTS = [
        'growvia_offer_view' => 'Offer viewed',
        'growvia_offer_add' => 'Added to cart from an offer',
        'growvia_purchase' => 'Purchase with an offer',
        'growvia_experiment_variant' => 'A/B test version seen',
    ];

    private const ENDPOINT = 'https://www.google-analytics.com';

    public static function available(Store $store): bool
    {
        return Features::enabled('integrations', $store) && $store->planIncludes('klaviyo_ga4');
    }

    public static function for(Store $store): ?StoreIntegration
    {
        return self::available($store) ? StoreIntegration::where('store_id', $store->id)->where('provider', 'ga4')->first() : null;
    }

    /** Checks the keys with Google's validation server, then saves them. Returns an error, or null. */
    public function connect(Store $store, string $measurementId, string $secret): ?string
    {
        $measurementId = strtoupper(trim($measurementId));
        if (! preg_match('/^G-[A-Z0-9]{4,16}$/', $measurementId)) {
            return 'The Measurement ID looks like G-XXXXXXXXXX (GA4 › Admin › Data streams).';
        }
        $error = $this->validate($measurementId, trim($secret));
        if ($error) {
            return $error;
        }
        $integration = StoreIntegration::updateOrCreate(['store_id' => $store->id, 'provider' => 'ga4'], [
            'credentials' => ['measurement_id' => $measurementId, 'api_secret' => trim($secret)],
            'settings' => ['events' => array_fill_keys(array_keys(self::EVENTS), true)], 'status' => 'connected', 'last_error' => null,
        ]);
        $integration->note("Connected to {$measurementId}.");

        return null;
    }

    /** Sends a test event to Google's validation server. Returns an error, or null. */
    public function test(StoreIntegration $i): ?string
    {
        $error = $this->validate($i->credentials['measurement_id'], $i->credentials['api_secret']);
        $i->note($error ? "Test failed: {$error}" : 'Test event accepted by GA4.', $error === null);

        return $error;
    }

    private function validate(string $measurementId, string $secret): ?string
    {
        if ($secret === '' || strlen($secret) > 100) {
            return 'Add the API secret (GA4 › Admin › Data streams › your stream › Measurement Protocol API secrets).';
        }
        try {
            $r = Http::timeout(10)->post(self::ENDPOINT.'/debug/mp/collect?'.http_build_query(['measurement_id' => $measurementId, 'api_secret' => $secret]),
                ['client_id' => '1234567890.1234567890', 'events' => [['name' => 'growvia_offer_view', 'params' => ['offer_id' => 'test', 'engagement_time_msec' => 1]]]]);
        } catch (Throwable) {
            return 'Couldn\'t reach Google Analytics. Try again in a minute.';
        }
        if (! $r->successful()) {
            return 'Google Analytics answered with an error ('.$r->status().').';
        }
        $messages = (array) $r->json('validationMessages', []);

        return $messages ? 'GA4 says: '.mb_substr((string) ($messages[0]['description'] ?? 'the event isn\'t valid'), 0, 200) : null;
    }

    /**
     * Sends events for one shopper (their GA client id). $events: [[name, params]]. Events the
     * merchant turned off are skipped.
     */
    public function send(StoreIntegration $i, string $clientId, array $events): void
    {
        $on = (array) $i->setting('events', []);
        $events = array_values(array_filter($events, fn ($e) => $on[$e[0]] ?? false));
        if (! $events || ! preg_match('/^\d{5,12}\.\d{5,12}$/', $clientId)) {
            return;
        }
        try {
            $r = Http::timeout(5)->post(self::ENDPOINT.'/mp/collect?'.http_build_query(['measurement_id' => $i->credentials['measurement_id'], 'api_secret' => $i->credentials['api_secret']]),
                ['client_id' => $clientId, 'events' => array_map(fn ($e) => ['name' => $e[0], 'params' => $e[1] + ['engagement_time_msec' => 1]], array_slice($events, 0, 25))]);
            $ok = $r->successful();
        } catch (Throwable) {
            $ok = false;
        }
        // One log line a day for sends (and every failure), so the log stays readable.
        $today = now()->toDateString();
        $counts = (array) $i->setting('sent', []);
        $first = ! isset($counts[$today]);
        $counts = [$today => ($counts[$today] ?? 0) + ($ok ? count($events) : 0)];
        $i->forceFill(['settings' => array_merge((array) $i->settings, ['sent' => $counts]), 'last_sync_at' => now()])->save();
        if (! $ok) {
            $i->note('Google Analytics didn\'t accept events. Check the API secret.', false);
        } elseif ($first) {
            $i->note('Sending events to GA4 today.');
        }
    }

    /** "GA1.1.123456789.1700000000" (the _ga cookie) → "123456789.1700000000". */
    public static function clientId(?string $cookie): ?string
    {
        return $cookie && preg_match('/(\d{5,12}\.\d{5,12})$/', $cookie, $m) ? $m[1] : null;
    }
}
