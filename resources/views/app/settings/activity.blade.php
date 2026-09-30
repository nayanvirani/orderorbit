@extends('layouts.embedded')

@section('title', 'Settings · Activity')

@php
    $labels = [
        'store.installed' => 'Installed OrderOrbit Space',
        'store.token_refreshed' => 'Shopify connection refreshed',
        'store.reconnected' => 'Reconnected Shopify',
        'store.capabilities_checked' => 'Re-checked store capabilities',
        'store.uninstalled' => 'Uninstalled OrderOrbit Space',
        'user.joined' => 'Joined OrderOrbit Space',
        'user.invited' => 'Invited a staff member',
        'user.invite_cancelled' => 'Cancelled an invite',
        'user.role_changed' => 'Changed a role',
        'user.access_removed' => 'Removed access',
        'user.access_restored' => 'Restored access',
        'onboarding.goal_selected' => 'Chose a goal',
        'billing.subscription_requested' => 'Started a plan change',
        'billing.plan_activated' => 'Plan activated',
    ];
@endphp

@section('content')
<s-page heading="Settings">
    @include('app.settings._tabs')

    <s-section heading="Activity">
        @if ($logs->isEmpty())
            <s-paragraph>No activity yet.</s-paragraph>
        @else
            <div class="oo-scroll">
                <table class="oo-table stack">
                    <thead><tr><th>When</th><th>Who</th><th>What</th><th>Details</th><th>Request ID</th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td title="{{ $log->created_at?->toDayDateTimeString() }}">{{ $log->created_at?->diffForHumans() }}</td>
                                <td data-label="Who">{{ $log->actor?->displayName() ?? 'OrderOrbit Space' }}</td>
                                <td>{{ $labels[$log->action] ?? $log->action }}</td>
                                <td class="oo-muted oo-small">{{ collect($log->context ?? [])->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.(is_array($v) ? implode(', ', $v) : $v))->implode(' · ') }}</td>
                                <td>@if ($log->request_id)<span class="oo-code">{{ \Illuminate\Support\Str::limit($log->request_id, 8, '') }}</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <s-stack direction="inline" gap="small-200" style="margin-top:12px">
                @if ($logs->currentPage() > 1)<s-button href="{{ app_route('app.settings.activity', ['page' => $logs->currentPage() - 1]) }}">Newer</s-button>@endif
                @if ($logs->hasMorePages())<s-button href="{{ app_route('app.settings.activity', ['page' => $logs->currentPage() + 1]) }}">Older</s-button>@endif
            </s-stack>
        @endif
    </s-section>
</s-page>
@endsection
