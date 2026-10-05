{{-- Plan cards from the super admin's plans: name, badge, tagline, price and what's included. --}}
<div class="plans plans-{{ count($plans) }}">
    @foreach ($plans as $key => $plan)
        <div class="plan {{ ! empty($plan['badge']) ? 'featured' : '' }} reveal">
            @if (! empty($plan['badge']))<span class="badge">{{ $plan['badge'] }}</span>@endif
            <h3>{{ $plan['name'] }}</h3>
            <div class="tagline">{{ $plan['description'] ?? '' }}</div>
            <div class="price">@if ($plan['price'] > 0)${{ number_format($plan['price'], 2) }}<small>/mo</small>@else Free @endif</div>
            <div class="muted" style="font-size:14px">{{ $plan['price'] > 0 ? 'Billed through Shopify' : 'Free forever' }}</div>
            <ul>
                @foreach ($plan['features'] ?? [] as $feature)
                    <li @class(['limit' => $loop->first])><x-icon name="check"/>{{ $feature }}</li>
                @endforeach
            </ul>
            <a class="btn {{ ! empty($plan['badge']) ? 'primary' : '' }}" href="{{ config('shopify.install_url') }}" data-event="plan_selected">{{ $plan['price'] > 0 ? 'Install on Shopify' : 'Start free' }}</a>
        </div>
    @endforeach
</div>
