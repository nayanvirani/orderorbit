<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Analytics\Analytics;
use App\Services\Analytics\PixelConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Spa\Page;
use Throwable;

class AnalyticsController extends Controller
{
    public function index(Request $request, Store $store, Analytics $analytics, PixelConnector $pixel): Page
    {
        // Connect the pixel the first time analytics is opened (also done on install).
        $error = null;
        if (! $pixel->connected($store)) {
            try {
                $pixel->connect($store);
            } catch (Throwable $e) {
                report($e);
                $error = $e->getMessage();
            }
        }

        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $summary = $analytics->summary($store, $days);

        $experiences = AnalyticsReportsController::experienceMap($store);
        $p = $summary['previous'];
        $trend = fn ($key) => Analytics::trend($summary[$key], $p[$key]);

        return page('analytics/index', AnalyticsReportsController::sharedProps($store, $days) + [
            'summary' => collect($summary)->except(['experiences', 'previous'])->all(),
            'trends' => ['influenced_revenue' => $trend('influenced_revenue'), 'influenced_orders' => $trend('influenced_orders'), 'revenue' => $trend('revenue'), 'aov' => $trend('aov'), 'conversion' => $trend('conversion')],
            'offers' => collect($summary['experiences'])->map(fn ($r, $handle) => $r + ['handle' => $handle, 'experience' => $experiences[$handle] ?? null])->sortByDesc('revenue')->values(),
            'connected' => $pixel->connected($store->fresh()),
            'error' => $error,
            'offerAnalytics' => $store->planIncludes('offer_analytics'),
        ]);
    }

    public function connect(Store $store, PixelConnector $pixel): RedirectResponse
    {
        try {
            $store->forceFill(['web_pixel_id' => null])->save();
            $pixel->connect($store);
            $notice = 'analytics_connected';
        } catch (Throwable $e) {
            report($e);
            $notice = 'shopify';
        }

        return redirect()->to(app_route('app.analytics', ['notice' => $notice]));
    }
}
