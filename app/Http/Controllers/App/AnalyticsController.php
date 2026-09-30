<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Analytics\Analytics;
use App\Services\Analytics\PixelConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AnalyticsController extends Controller
{
    public function index(Request $request, Store $store, Analytics $analytics, PixelConnector $pixel): View
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

        return view('app.analytics.index', [
            'store' => $store,
            'summary' => $summary,
            'connected' => $pixel->connected($store->fresh()),
            'error' => $error,
            'experiences' => $store->experiences()->where('status', '!=', 'archived')->get()->keyBy('handle'),
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
