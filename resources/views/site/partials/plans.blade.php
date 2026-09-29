<div class="grid" style="margin-top:24px">
    @foreach ($plans as $key => $plan)
        <div class="card {{ $key === 'growth' ? 'featured' : '' }}">
            <h3>{{ $plan['name'] }}</h3>
            <div class="price">${{ number_format($plan['price'], 2) }}<small>/mo</small></div>
            <ul>
                @foreach ($plan['features'] as $feature)
                    <li>{{ $feature }}</li>
                @endforeach
            </ul>
            <a class="btn {{ $key === 'growth' ? 'primary' : '' }}" href="{{ config('shopify.install_url') }}">Install on Shopify</a>
        </div>
    @endforeach
</div>
<p class="muted" style="text-align:center;margin-top:16px">Billed through Shopify.</p>
