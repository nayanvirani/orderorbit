<?php

namespace App\Support;

use App\Models\Store;
use App\Services\Analytics\Analytics;
use Illuminate\Support\Facades\Cache;

/**
 * Review request (G23): after a real milestone, Home shows a milestone card and then asks Shopify's
 * App Bridge for its review window (shopify.reviews.request), never from a button. Shopify decides
 * whether to show it and enforces its own limits; Growvia also waits 60 days between asks and
 * never asks in the first 24 hours after install. The answer code is kept on the store.
 */
class ReviewPrompt
{
    public const GAP_DAYS = 60;

    /** The milestone to celebrate now, or null. */
    public static function milestone(Store $store): ?array
    {
        if (! Features::enabled('review_prompt', $store) || ! $store->installed_at || $store->installed_at->gt(now()->subDay())
            || ($store->review_prompted_at && $store->review_prompted_at->gt(now()->subDays(self::GAP_DAYS)))) {
            return null;
        }

        return Cache::remember("review-milestone:{$store->id}", 3600, function () use ($store) {
            $analytics = app(Analytics::class);
            $year = $analytics->totals($store, now()->subDays(365), now());
            $currency = $store->currency ?: 'USD';
            if ((float) $year['influenced_revenue'] >= 1000 && $store->review_milestone !== 'first_1000') {
                return ['key' => 'first_1000', 'title' => 'Your offers passed '.self::money(1000, $currency).' in sales', 'text' => 'Orders with a Growvia offer have now brought in '.self::money((float) $year['influenced_revenue'], $currency).'. Nice work.',
                    'results' => route('app.analytics', [], false), 'more' => route('app.cro.overview', [], false)];
            }
            // The widget that earned the most in the last 30 days, live for at least a week.
            $top = \App\Models\AnalyticsEvent::where('store_id', $store->id)->where('event', 'attributed')->where('occurred_at', '>=', now()->subDays(30))
                ->whereNotNull('experience_handle')->selectRaw('experience_handle, coalesce(sum(value), 0) as total')->groupBy('experience_handle')->orderByDesc('total')->first();
            $widget = $top ? $store->experiences()->where('handle', $top->experience_handle)->where('status', 'published')->where('published_at', '<=', now()->subDays(7))->first() : null;
            if ($widget && (float) $top->total > 0 && ! $store->review_milestone) {
                return ['key' => 'widget_week', 'title' => 'Your '.$widget->name.' widget earned '.self::money((float) $top->total, $currency).' in the last 30 days',
                    'text' => 'Nice work. See which products it sold most, or add it to more pages.',
                    'results' => route('app.cro.experiences.show', ['experience' => $widget->id], false),
                    'more' => in_array($widget->type, ['bundles', 'progressive-gifts'], true) ? null : route('app.cro.experiences.edit', ['experience' => $widget->id], false)];
            }

            return null;
        });
    }

    /** Records what Shopify answered (shown, or why not), so the next ask waits. */
    public static function record(Store $store, string $milestone, string $code): void
    {
        $store->forceFill(['review_milestone' => mb_substr($milestone, 0, 40), 'review_prompted_at' => now(), 'review_result' => mb_substr($code, 0, 60)])->save();
        Cache::forget("review-milestone:{$store->id}");
    }

    private static function money(float $amount, string $currency): string
    {
        $symbol = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹', 'CAD' => 'CA$', 'AUD' => 'A$', 'NZD' => 'NZ$', 'JPY' => '¥'][$currency] ?? $currency.' ';

        return $symbol.number_format($amount, $amount >= 1000 || floor($amount) == $amount ? 0 : 2);
    }
}
