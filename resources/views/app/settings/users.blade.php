@extends('layouts.embedded')

@section('title', 'Settings · Users & roles')

@section('content')
<s-page inlineSize="large" heading="Settings">
    @include('app.settings._tabs')

    <s-section heading="Staff">
        <s-paragraph>Everyone with access to OrderOrbit Space in Shopify admin appears here the first time they open the app. Give someone a role in advance by inviting their Shopify staff email.</s-paragraph>
        <div class="oo-scroll">
            <table class="oo-table stack">
                <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last active</th><th></th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        @php($locked = $user->is($me) || $user->account_owner || ($user->role === 'owner' && $me->role !== 'owner'))
                        <tr>
                            <td>
                                <strong>{{ $user->displayName() }}</strong>
                                @if ($user->is($me))<span class="oo-muted"> (you)</span>@endif
                                @if ($user->email && $user->displayName() !== $user->email)<div class="oo-muted oo-small">{{ $user->email }}</div>@endif
                                @if ($user->account_owner)<div class="oo-muted oo-small">Shopify store owner</div>@endif
                            </td>
                            <td>
                                @if ($locked || $user->disabled_at)
                                    {{ \App\Support\Permissions::ROLES[$user->role] }}
                                @else
                                    <form method="POST" action="{{ app_route('app.settings.users.role', ['user' => $user->id]) }}">
                                        <select class="oo-select" name="role" data-autosubmit aria-label="Role for {{ $user->displayName() }}">
                                            @foreach ($assignable as $role)
                                                <option value="{{ $role }}" @selected($user->role === $role)>{{ \App\Support\Permissions::ROLES[$role] }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            </td>
                            <td>
                                @switch($user->status())
                                    @case('active')<s-badge tone="success">Active</s-badge>@break
                                    @case('pending')<s-badge tone="info">Pending invite</s-badge>@break
                                    @case('removed')<s-badge tone="critical">Access removed</s-badge>@break
                                @endswitch
                            </td>
                            <td class="oo-muted" data-label="Last active">{{ $user->last_active_at?->diffForHumans() ?? '—' }}</td>
                            <td style="text-align:right">
                                @unless ($locked)
                                    @if ($user->disabled_at)
                                        <form method="POST" action="{{ app_route('app.settings.users.restore', ['user' => $user->id]) }}"><s-button type="submit" variant="tertiary">Restore access</s-button></form>
                                    @else
                                        <form method="POST" action="{{ app_route('app.settings.users.remove', ['user' => $user->id]) }}" data-confirm="{{ $user->isPendingInvite() ? 'Cancel this invite?' : 'Remove '.$user->displayName().'\'s access to OrderOrbit Space?' }}">
                                            <s-button type="submit" variant="tertiary" tone="critical">{{ $user->isPendingInvite() ? 'Cancel invite' : 'Remove' }}</s-button>
                                        </form>
                                    @endif
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </s-section>

    <s-section heading="Invite staff">
        <form method="POST" action="{{ app_route('app.settings.users.invite') }}" class="oo-form-row">
            <label class="oo-field" style="flex:1 1 240px">Shopify staff email<input type="email" name="email" required placeholder="name@yourstore.com"></label>
            <label class="oo-field">Role
                <select name="role">@foreach ($assignable as $role)<option value="{{ $role }}" @selected($role === 'staff')>{{ \App\Support\Permissions::ROLES[$role] }}</option>@endforeach</select>
            </label>
            <s-button type="submit" variant="primary">Invite</s-button>
        </form>
        <s-paragraph><span class="oo-muted oo-small">They also need access to OrderOrbit Space in Shopify admin (Settings → Users). When they first open the app, they get this role.</span></s-paragraph>
    </s-section>

    <s-section heading="What each role can do">
        <div class="oo-scroll">
            <table class="oo-table">
                <thead><tr><th>Permission</th>@foreach (\App\Support\Permissions::ROLES as $label)<th style="text-align:center">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach (\App\Support\Permissions::MATRIX as $permission)
                        <tr><td>{{ $permission['label'] }}</td>@foreach (array_keys(\App\Support\Permissions::ROLES) as $role)<td style="text-align:center">{!! in_array($role, $permission['roles'], true) ? '✓' : '<span class="oo-muted">—</span>' !!}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </s-section>
</s-page>
@endsection
