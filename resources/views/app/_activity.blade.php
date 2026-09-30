{{-- Recent activity list (dashboard). --}}
@if ($recent->isEmpty())
    <p class="oo-muted" style="margin:0">Your team's changes — new offers, publishes, plan changes — show up here.</p>
@else
    <ul class="ob-activity">
        @foreach ($recent as $log)
            <li>
                <x-app.icon :name="str_starts_with($log->action, 'billing') ? 'card' : (str_starts_with($log->action, 'experience') ? 'sparkle' : 'settings')" size="sm" :tone="str_starts_with($log->action, 'experience') ? 'analytics' : 'settings'" />
                <div><strong>{{ $log->actor?->displayName() ?? 'OrderOrbit Space' }}</strong> {{ $actionLabels[$log->action] ?? str_replace(['.', '_'], [' ', ' '], $log->action) }}
                    <time>{{ $log->created_at?->diffForHumans() }}</time></div>
            </li>
        @endforeach
    </ul>
    @if (request()->attributes->get('storeUser')?->can('view_activity'))
        <s-link href="{{ app_route('app.settings.activity') }}">View all activity</s-link>
    @endif
@endif
