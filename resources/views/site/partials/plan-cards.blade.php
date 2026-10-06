{{-- Plan cards from Plans & features (name, badge, tagline, price, lines); labels from the Pricing page copy. --}}
@php($p = \App\Support\SiteContent::page('pricing'))
<div class="plans">
    @foreach ($plans as $key => $plan)
        @php($featured = ! empty($plan['badge']))
        <div class="plan {{ $featured ? 'featured' : '' }} reveal">
            <div class="head">
                <div><h3>{{ $plan['name'] }}</h3>@if (! empty($plan['description']))<p class="tagline">{{ $plan['description'] }}</p>@endif</div>
                @if ($featured)<span class="badge">{{ $plan['badge'] }}</span>@endif
            </div>
            <div>
                <span class="price">@if ($plan['price'] > 0)${{ number_format($plan['price'], 2) }}<small>{{ $p['per_month'] }}</small>@else{{ $p['free'] }}@endif</span>
                <span class="billing">{{ $plan['price'] > 0 ? $p['paid_note'] : $p['free_note'] }}</span>
            </div>
            <a class="btn {{ $featured ? 'primary' : 'secondary' }} block" href="{{ config('shopify.install_url') }}" data-event="plan_selected">{{ $plan['price'] > 0 ? $p['paid_button'] : $p['free_button'] }}</a>
            <ul>
                @foreach (\App\Support\Plans::lines($plan) as $line)
                    <li @class(['limit' => $loop->first])><x-icon name="check"/>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
