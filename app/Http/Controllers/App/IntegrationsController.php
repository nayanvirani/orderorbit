<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\WebhookReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Spa\Page;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settings → Integrations (connection, web pixel, webhooks, extensions) and Settings → Privacy
 * (consent, retention, collection controls, export and deletion).
 */
class IntegrationsController extends Controller
{
    /** Webhook topics the app subscribes to (shopify.app.toml). */
    public const TOPICS = [
        'app/uninstalled' => 'App uninstalled', 'app/scopes_update' => 'Permissions changed', 'app_subscriptions/update' => 'Plan changes',
        'orders/create' => 'Orders created', 'orders/updated' => 'Orders updated', 'orders/cancelled' => 'Orders cancelled', 'orders/paid' => 'Orders paid',
        'orders/fulfilled' => 'Orders fulfilled', 'refunds/create' => 'Refunds', 'customers/create' => 'Customers created', 'customers/update' => 'Customers updated',
        'customers/data_request' => 'Customer data requests', 'customers/redact' => 'Customer data deletion', 'shop/redact' => 'Store data deletion',
    ];

    public function integrations(Store $store): Page
    {
        $receipts = WebhookReceipt::where('shop_domain', $store->shop_domain)->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('topic, max(created_at) as last_at, count(*) as n, sum(case when processed_at is null then 1 else 0 end) as unprocessed')
            ->groupBy('topic')->get()->keyBy('topic');
        $lastEvent = AnalyticsEvent::where('store_id', $store->id)->max('occurred_at');

        return page('settings/integrations', [
            'connection' => [
                'connected' => $store->isInstalled() && ! $store->missingScopes(),
                'themeBlocks' => $store->capability('online_store_2') !== false,
                'pixel' => (bool) $store->web_pixel_id,
                'lastPixelEvent' => $lastEvent ? Carbon::parse($lastEvent) : null,
                'checkoutBlocks' => (bool) $store->capability('checkout_blocks'),
                'customerAccounts' => $store->capability('new_customer_accounts') !== false,
                'themeEditorUrl' => $store->themeEditorUrl('global'),
            ],
            'webhooks' => collect(self::TOPICS)->map(fn ($label, $topic) => [
                'topic' => $topic, 'label' => $label,
                'received' => (int) ($receipts[$topic]->n ?? 0),
                'last_at' => isset($receipts[$topic]) ? Carbon::parse($receipts[$topic]->last_at) : null,
                'unprocessed' => (int) ($receipts[$topic]->unprocessed ?? 0),
            ])->values(),
        ]);
    }

    public function privacy(Store $store): Page
    {
        $oldest = AnalyticsEvent::where('store_id', $store->id)->min('occurred_at');

        return page('settings/privacy', [
            'privacy' => collect(array_keys(Store::PRIVACY_DEFAULTS))->mapWithKeys(fn ($k) => [$k => $store->privacy($k)])->all(),
            'events' => AnalyticsEvent::where('store_id', $store->id)->count(),
            'oldest' => $oldest ? Carbon::parse($oldest) : null,
            'policies' => collect(\App\Support\Legal::pages())->map(fn ($p) => [
                'title' => $p['title'], 'url' => \App\Support\Legal::url($p['slug']), 'version' => $p['version'],
                'accepted' => $store->legal_acks[$p['slug']] ?? null,
            ])->values()->all(),
        ]);
    }

    public function updatePrivacy(Request $request, Store $store): RedirectResponse
    {
        $privacy = [
            'retention_months' => in_array((int) $request->input('retention_months'), [3, 6, 13], true) ? (int) $request->input('retention_months') : 13,
            'browsing_events' => $request->boolean('browsing_events'),
            'journeys' => $request->boolean('journeys'),
        ];
        $store->forceFill(['privacy' => $privacy])->save();
        AuditLog::record('settings.privacy_updated', $store, $privacy);

        return redirect()->to(app_route('app.settings.privacy', ['notice' => 'saved']));
    }

    /** All analytics events for this store, as CSV (no personal data is stored). */
    public function export(Store $store): StreamedResponse
    {
        AuditLog::record('settings.analytics_exported', $store);

        return response()->streamDownload(function () use ($store) {
            $out = fopen('php://output', 'w');
            $columns = ['occurred_at', 'name', 'event', 'experience_handle', 'experience_type', 'template', 'visitor_id', 'session_id', 'customer_id', 'page_type', 'product_id', 'variant_id', 'device', 'country', 'source', 'medium', 'campaign', 'value', 'currency', 'quantity', 'order_ref', 'label', 'experiment_handle', 'variant'];
            fputcsv($out, $columns);
            AnalyticsEvent::where('store_id', $store->id)->orderBy('id')->select($columns)->chunk(2000, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('c') : (is_array($v) ? json_encode($v) : $v), array_values($row->getAttributes())));
                }
            });
            fclose($out);
        }, 'orderorbit-analytics-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function destroyAnalytics(Store $store): RedirectResponse
    {
        $deleted = AnalyticsEvent::where('store_id', $store->id)->delete();
        AuditLog::record('settings.analytics_deleted', $store, ['events' => $deleted]);

        return redirect()->to(app_route('app.settings.privacy', ['notice' => 'analytics_deleted']));
    }
}
