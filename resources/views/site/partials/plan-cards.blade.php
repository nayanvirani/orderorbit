@php($pricing = \App\Support\Content::pricing())
<div class="plans">
    @foreach ($plans as $key => $plan)
        <div class="plan {{ $key === 'growth' ? 'featured' : '' }} reveal">
            @if ($key === 'growth')<span class="badge">MOST POPULAR</span>@endif
            <h3>{{ $plan['name'] }}</h3>
            <div class="tagline">{{ $pricing['taglines'][$key] }}</div>
            <div class="price">${{ number_format($plan['price'], 2) }}<small>/mo</small></div>
            <div class="muted" style="font-size:14px">Billed through Shopify</div>
            <ul>
                @foreach ($plan['features'] as $feature)
                    <li><x-icon name="check"/>{{ $feature }}</li>
                @endforeach
            </ul>
            <a class="btn {{ $key === 'growth' ? 'primary' : '' }}" href="{{ config('shopify.install_url') }}" data-event="plan_selected">Install on Shopify</a>
        </div>
    @endforeach
</div>
