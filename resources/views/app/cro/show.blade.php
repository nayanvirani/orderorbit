@extends('layouts.embedded')

@section('title', $experience->name)

@php
    $type = \App\Experiences\Registry::type($experience->type);
    $published = $experience->publishedVersion;
    $config = $experience->draft_config;
    // Bundles and progressive gifts keep their settings in their own editors.
    $module = match ($experience->type) {
        'bundles' => ['route' => app_route('app.bundles.edit', ['bundle' => $experience->id]), 'summary' => \App\Experiences\BundleSchema::TYPES[\App\Experiences\BundleSchema::normalize($config)[0]['bundle_type']]['label'] ?? 'Bundle'],
        'progressive-gifts' => ['route' => app_route('app.gifts.edit', ['gift' => $experience->id]), 'summary' => collect(\App\Experiences\GiftSchema::normalize($config)[0]['milestones'])->pluck('label')->implode(' · ')],
        default => null,
    };
    $tabs = $module
        ? ['overview' => 'Overview', 'configuration' => 'Configuration', 'analytics' => 'Analytics', 'history' => 'History']
        : ['overview' => 'Overview', 'configuration' => 'Configuration', 'targeting' => 'Targeting', 'analytics' => 'Analytics', 'experiment' => 'Experiment', 'history' => 'History'];
    $canEdit = request()->attributes->get('storeUser')?->can('manage_experiences');
    $global = $type['surface'] === 'global';
    $editorUrl = $store->themeEditorUrl($type['surface']);
    $describe = function ($field, $value) {
        return match ($field['type']) {
            'toggle' => $value ? 'On' : 'Off',
            'select' => $field['options'][$value] ?? $value,
            'checkboxes' => collect((array) $value)->map(fn ($v) => $field['options'][$v] ?? $v)->implode(', ') ?: '—',
            'products', 'collections' => collect((array) $value)->pluck('title')->implode(', ') ?: '—',
            'list' => count((array) $value).' '.\Illuminate\Support\Str::plural('row', count((array) $value)),
            'datetime' => $value ? \Illuminate\Support\Carbon::parse($value)->toDayDateTimeString() : '—',
            default => ($value === null || $value === '') ? '—' : $value,
        };
    };
    $actionLabels = ['experience.created' => 'Created', 'experience.saved' => 'Saved draft', 'experience.published' => 'Published', 'experience.paused' => 'Paused', 'experience.resumed' => 'Resumed', 'experience.archived' => 'Archived', 'experience.unarchived' => 'Restored from archive', 'experience.duplicated' => 'Created as a copy', 'experience.version_restored' => 'Loaded an earlier version', 'experience.changes_discarded' => 'Discarded changes'];
@endphp

@section('content')
<s-page inlineSize="large" heading="{{ $experience->name }}">
    @if ($featureKey = \App\Experiences\Registry::featureFor($experience->type))
        <s-link slot="breadcrumb-actions" href="{{ app_route('app.features.show', ['feature' => $featureKey]) }}">{{ \App\Experiences\Registry::feature($featureKey)['label'] }}</s-link>
    @endif
    @if ($experience->shopify_discount_id)
        <s-banner tone="success" heading="Saving applies automatically at checkout">
            <s-paragraph>OrderOrbit Space created a Shopify automatic discount for this {{ lower_label($type['singular']) }}. It stays in step with this experience: pausing or archiving removes it.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ $store->adminUrl('discounts/'.preg_replace('/\D/', '', $experience->shopify_discount_id)) }}" target="_top">View in Shopify</s-button>
        </s-banner>
    @endif
    @if ($canEdit && $experience->status !== 'archived')
        <s-button slot="primary-action" variant="primary" href="{{ app_route('app.cro.experiences.edit', ['experience' => $experience->id]) }}">Edit</s-button>
    @endif

    @if ($experience->type === 'sales-pop')
        @php
            $recent = \App\Models\RecentPurchase::where('store_id', $store->id)->where('purchased_at', '>=', now()->subDays((int) ($experience->draft_config['content']['max_age_days'] ?? 7)));
            $recentCount = $recent->count();
            $canReadOrders = $store->hasScope('read_orders');
            $ordersBlocked = $canReadOrders && \App\Services\SalesPop\RecentOrders::blocked($store);
        @endphp
        <s-banner tone="{{ $recentCount ? 'success' : ($ordersBlocked ? 'warning' : 'info') }}" heading="{{ $recentCount ? $recentCount.' recent '.\Illuminate\Support\Str::plural('purchase', $recentCount).' ready to show' : ($ordersBlocked ? 'Shopify needs to approve order access' : 'No recent purchases to show yet') }}">
            <s-paragraph>
                @if ($ordersBlocked)
                    Shopify only lets apps read orders after the developer declares how order data is used. In the Shopify Partner Dashboard, open OrderOrbit Space → API access → Protected customer data, request access to order data (no names, emails or addresses are needed) and save. Then click Import recent orders again.
                @elseif ($recentCount)
                    Pops cycle through products from your real orders in the last {{ (int) ($experience->draft_config['content']['max_age_days'] ?? 7) }} days. New orders are added automatically.
                @elseif ($canReadOrders)
                    Pops appear once your store has an order in the last {{ (int) ($experience->draft_config['content']['max_age_days'] ?? 7) }} days. New orders are added automatically, or import your recent orders now.
                @else
                    Pops use your real orders. Reload OrderOrbit Space and approve the updated permissions (read orders) so new orders are added automatically.
                @endif
            </s-paragraph>
            @if ($canReadOrders)
                <form slot="secondary-actions" method="POST" action="{{ app_route('app.cro.experiences.import-orders', ['experience' => $experience->id]) }}"><s-button type="submit">Import recent orders</s-button></form>
            @endif
        </s-banner>
    @endif

    @if ($experience->status === 'published' && $experience->placement_status === 'not_placed')
        <s-banner tone="warning" heading="Published but not placed">
            <s-paragraph>{{ match (true) {
                $global => 'Shoppers can\'t see this yet. Turn on the OrderOrbit Space app embed in the Theme Editor (App embeds), then save.',
                $type['surface'] === 'post-purchase' => 'Shoppers can\'t see this yet. In Shopify, open Settings → Checkout and choose OrderOrbit Space under Post-purchase page.',
                $type['surface'] === 'account' => 'Customers can\'t see this yet. In Shopify\'s customer accounts editor, add the OrderOrbit Space account block and set its type to “'.$experience->type.'”.',
                in_array($type['surface'], \App\Experiences\Schema::CHECKOUT_SURFACES, true) => 'Shoppers can\'t see this yet. In Shopify\'s checkout editor, add the OrderOrbit Space block and set its type to “'.$experience->type.'”.',
                default => 'Shoppers can\'t see this yet. Add the OrderOrbit Space block in the Theme Editor and choose “'.$type['singular'].'”.',
            } }}</s-paragraph>
            <s-button slot="secondary-actions" href="{{ $editorUrl }}" target="_top">{{ $store->editorLabel($type['surface']) }}</s-button>
        </s-banner>
    @endif
    @if ($published && $experience->has_unpublished_changes && $experience->status !== 'archived')
        <s-banner tone="info">This experience has changes that aren't live yet. Publish from the editor to update your store.</s-banner>
    @endif

    <nav class="oo-tabs" aria-label="Experience">
        @foreach ($tabs as $key => $label)
            <a href="{{ app_route('app.cro.experiences.show', ['experience' => $experience->id, 'tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'overview')
        <s-section>
            <dl class="oo-kv">
                <dt>Status</dt><dd>@include('app.cro._status')</dd>
                <dt>Type</dt><dd>{{ $type['label'] }}</dd>
                <dt>Template</dt><dd>{{ $experience->templateName() }} @if ($experience->templateVersion)<span class="oo-muted">· v{{ $experience->templateVersion->version }}</span>@endif</dd>
                <dt>Experience ID</dt><dd><span class="oo-code">{{ $experience->handle }}</span> <span class="oo-muted oo-small">Pin it in the block's “Experience ID” setting.</span></dd>
                <dt>Live version</dt><dd>{{ $published ? 'v'.$published->version.' · '.$published->published_at->diffForHumans() : 'Not published' }}</dd>
                @if ($experience->starts_at || $experience->ends_at)
                    <dt>Schedule</dt><dd>{{ $experience->starts_at?->setTimezone($store->timezone ?? 'UTC')->toDayDateTimeString() ?? 'Now' }} → {{ $experience->ends_at?->setTimezone($store->timezone ?? 'UTC')->toDayDateTimeString() ?? 'No end' }}</dd>
                @endif
                <dt>Placement</dt>
                <dd>
                    @switch($experience->placement_status)
                        @case('placed')<s-badge tone="success">Placed in theme</s-badge>@break
                        @case('not_placed')<s-badge tone="critical">Not placed</s-badge>@break
                        @case('external')<s-badge>Placed in Shopify checkout settings</s-badge>@break
                        @default<s-badge>Not checked</s-badge>
                    @endswitch
                    <span class="oo-muted oo-small">{{ $experience->placement_checked_at ? 'Checked '.$experience->placement_checked_at->diffForHumans() : '' }}</span>
                </dd>
                @if ($experience->description)<dt>Description</dt><dd>{{ $experience->description }}</dd>@endif
            </dl>
        </s-section>

        @if ($experience->type === 'ty-survey')
            @php
                $answers = \App\Models\AnalyticsEvent::where('store_id', $store->id)->where('experience_handle', $experience->handle)->where('event', 'survey')
                    ->where('occurred_at', '>=', now()->subDays(30))->whereNotNull('label')
                    ->selectRaw('label, count(*) as total')->groupBy('label')->orderByDesc('total')->get();
                $answerTotal = max(1, $answers->sum('total'));
            @endphp
            <s-section heading="Answers · last 30 days">
                @if ($answers->isEmpty())
                    <s-paragraph><span class="oo-muted">Answers appear here as customers reply on the Thank You page. Only shoppers who allow analytics are counted.</span></s-paragraph>
                @else
                    <table class="oo-table">
                        <thead><tr><th>Answer</th><th>Replies</th><th>Share</th></tr></thead>
                        <tbody>
                            @foreach ($answers as $row)
                                <tr><td>{{ $row->label }}</td><td>{{ number_format($row->total) }}</td><td>{{ round($row->total / $answerTotal * 100) }}%</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </s-section>
        @endif

        @php($perf = app(\App\Services\Analytics\Analytics::class)->forExperiences($store, [$experience->handle])[$experience->handle] ?? ['views' => 0, 'clicks' => 0, 'adds' => 0, 'orders' => 0, 'revenue' => 0])
        <s-section heading="Performance · last 30 days">
            <div class="ob-kpis">
                <div class="ob-kpi"><small>Views</small><b>{{ number_format($perf['views']) }}</b></div>
                <div class="ob-kpi"><small>Added to cart</small><b>{{ number_format($perf['adds']) }}</b></div>
                <div class="ob-kpi"><small>Orders</small><b>{{ number_format($perf['orders']) }}</b></div>
                <div class="ob-kpi"><small>Revenue</small><b>{{ money($perf['revenue'], $store->currency) }}</b></div>
            </div>
            <s-paragraph><span class="oo-muted">From the OrderOrbit Space pixel, for shoppers who allow analytics. <s-link href="{{ app_route('app.analytics') }}">All analytics</s-link></span></s-paragraph>
        </s-section>

        @if ($canEdit)
            <s-section heading="Actions">
                <div class="oo-inline">
                    @if (in_array($experience->status, ['draft', 'paused'], true) && ! $published)
                        <s-button href="{{ app_route('app.cro.experiences.edit', ['experience' => $experience->id]) }}">Open editor to publish</s-button>
                    @endif
                    @if ($experience->status === 'published')
                        <form method="POST" action="{{ app_route('app.cro.experiences.lifecycle', ['experience' => $experience->id, 'action' => 'pause']) }}" data-confirm="Pause this experience? Shoppers stop seeing it right away."><s-button type="submit">Pause</s-button></form>
                    @endif
                    @if ($experience->status === 'paused' && $published)
                        <form method="POST" action="{{ app_route('app.cro.experiences.lifecycle', ['experience' => $experience->id, 'action' => 'resume']) }}"><s-button type="submit">Resume</s-button></form>
                    @endif
                    <s-button href="{{ $editorUrl }}" target="_top">{{ $store->editorLabel($type['surface']) }}</s-button>
                    <form method="POST" action="{{ app_route('app.cro.experiences.placement', ['experience' => $experience->id]) }}"><s-button type="submit">Re-check placement</s-button></form>
                    @php($activeTest = \App\Models\Experiments\Experiment::where('experience_id', $experience->id)->whereIn('status', ['running', 'paused', 'draft'])->latest('id')->first())
                    @if ($activeTest)
                        <s-button href="{{ app_route($activeTest->status === 'draft' ? 'app.experiments.edit' : 'app.experiments.show', ['experiment' => $activeTest->id]) }}">{{ $activeTest->status === 'draft' ? 'Continue A/B test setup' : 'View A/B test' }}</s-button>
                    @elseif ($experience->status === 'published' && ! \App\Services\Experiments\ExperimentManager::unsupported($experience))
                        <form method="POST" action="{{ app_route('app.experiments.store') }}"><input type="hidden" name="experience_id" value="{{ $experience->id }}"><s-button type="submit">Create A/B test</s-button></form>
                    @endif
                    <form method="POST" action="{{ app_route('app.cro.experiences.duplicate', ['experience' => $experience->id]) }}"><s-button type="submit">Duplicate</s-button></form>
                    @if ($experience->status === 'archived')
                        <form method="POST" action="{{ app_route('app.cro.experiences.lifecycle', ['experience' => $experience->id, 'action' => 'unarchive']) }}"><s-button type="submit">Restore from archive</s-button></form>
                    @else
                        <form method="POST" action="{{ app_route('app.cro.experiences.lifecycle', ['experience' => $experience->id, 'action' => 'archive']) }}" data-confirm="Archive this experience? It stops showing on your store."><s-button type="submit" tone="critical">Archive</s-button></form>
                    @endif
                </div>
            </s-section>
        @endif

    @elseif ($module && in_array($tab, ['configuration', 'targeting', 'experiment'], true))
        <s-section heading="Configuration">
            <s-paragraph>{{ $module['summary'] }}</s-paragraph>
            <s-paragraph><span class="oo-muted">Offers, products, settings and design are managed in the editor.</span></s-paragraph>
            @if ($canEdit && $experience->status !== 'archived')
                <s-button variant="primary" href="{{ $module['route'] }}">Open editor</s-button>
            @endif
        </s-section>

    @elseif ($tab === 'configuration' || $tab === 'targeting')
        @foreach ($tab === 'configuration' ? ['content', 'design', 'behavior'] : ['targeting', 'schedule'] as $section)
            <s-section heading="{{ ucfirst($section) }}">
                <dl class="oo-kv">
                    @foreach ($fields[$section] as $key => $field)
                        <dt>{{ $field['label'] }}</dt><dd>{{ $describe($field, $config[$section][$key] ?? null) }}</dd>
                    @endforeach
                </dl>
            </s-section>
        @endforeach
        @if ($canEdit && $experience->status !== 'archived')
            <s-button href="{{ app_route('app.cro.experiences.edit', ['experience' => $experience->id]) }}">Edit</s-button>
        @endif

    @elseif ($tab === 'analytics')
        <s-section heading="Analytics">
            <dl class="oo-kv">
                <dt>Track views</dt><dd>{{ ($config['analytics']['track_views'] ?? true) ? 'On' : 'Off' }}</dd>
                <dt>Track clicks</dt><dd>{{ ($config['analytics']['track_clicks'] ?? true) ? 'On' : 'Off' }}</dd>
            </dl>
            <s-paragraph><span class="oo-muted">Views, adds to cart, orders and revenue for this offer are on the Overview tab and in <s-link href="{{ app_route('app.analytics') }}">Analytics</s-link>.</span></s-paragraph>
        </s-section>

    @elseif ($tab === 'experiment')
        <s-section heading="Experiment">
            <s-paragraph>No test is running on this experience.</s-paragraph>
            <s-paragraph><span class="oo-muted">A/B and A/B/C tests arrive with Experiments.</span></s-paragraph>
        </s-section>

    @else
        <s-section heading="Versions">
            @if ($versions->isEmpty())
                <s-paragraph>No published versions yet.</s-paragraph>
            @else
                <table class="oo-table stack">
                    <thead><tr><th>Version</th><th>Published</th><th>By</th><th>Note</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($versions as $version)
                            <tr>
                                <td><strong>v{{ $version->version }}</strong> @if ($version->id === $experience->published_version_id)<s-badge tone="success">Live</s-badge>@endif</td>
                                <td data-label="Published">{{ $version->published_at?->diffForHumans() }}</td>
                                <td data-label="By">{{ $version->author?->displayName() ?? '—' }}</td>
                                <td class="oo-muted" data-label="Note">{{ $version->change_note ?? '—' }}</td>
                                <td style="text-align:right">
                                    @if ($canEdit && $version->id !== $experience->published_version_id)
                                        <form method="POST" action="{{ app_route('app.cro.experiences.versions.restore', ['experience' => $experience->id, 'version' => $version->id]) }}" data-confirm="Load v{{ $version->version }} into the draft? Your current draft is replaced."><s-button type="submit" variant="tertiary">Restore</s-button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </s-section>
        <s-section heading="Activity">
            @forelse ($activity as $log)
                <s-paragraph><strong>{{ $log->actor?->displayName() ?? 'OrderOrbit Space' }}</strong> · {{ $actionLabels[$log->action] ?? $log->action }}@if (isset($log->context['version'])) v{{ $log->context['version'] }}@endif <span class="oo-muted">· {{ $log->created_at->diffForHumans() }}</span></s-paragraph>
            @empty
                <s-paragraph>No activity yet.</s-paragraph>
            @endforelse
        </s-section>
    @endif
</s-page>
@endsection
