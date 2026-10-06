{{-- Small illustration at the top of a feature card, by feature. Purely decorative. --}}
@switch($slug)
    @case('bundles')
        <div class="peek" aria-hidden="true"><span class="bar"></span><span class="bar on"></span><span class="bar"></span></div>
        @break
    @case('progressive-gifts')
        <div class="peek" aria-hidden="true"><strong style="font-size:13px;color:var(--c-heading)">$15.00 to your free gift</strong><span class="meter" style="height:10px;background:var(--c-surface)"><span style="width:64%"></span></span></div>
        @break
    @case('cart-upsells')
        <div class="peek" aria-hidden="true"><span class="mini" style="flex-direction:row;align-items:center;gap:10px;padding:12px"><span style="width:36px;height:36px;border-radius:8px;background:var(--accent-soft)"></span><span style="flex:1;font-size:13px"><b>Add the matching item</b><br><span style="color:var(--c-success)">Save 10%</span></span><span class="btn ink sm" style="min-height:32px;padding:0 10px">+ Add</span></span></div>
        @break
    @case('countdown-timer')
        <div class="peek ink" aria-hidden="true"><span class="timer-tiles"><b style="font-size:22px;padding:12px 14px">02</b><b style="font-size:22px;padding:12px 14px">14</b><b style="font-size:22px;padding:12px 14px">09</b><b style="font-size:22px;padding:12px 14px;background:var(--c-accent)">58</b></span></div>
        @break
    @case('sticky-add-to-cart')
        <div class="peek" style="justify-content:flex-end;padding-bottom:20px" aria-hidden="true"><span class="mini" style="flex-direction:row;align-items:center;gap:10px;padding:10px 12px;font-size:13px"><span style="flex:1"><b>Product name</b> · $29.00</span><span class="btn primary sm" style="min-height:32px;padding:0 10px">Add to cart</span></span></div>
        @break
    @case('preorder')
        <div class="peek" aria-hidden="true"><span class="badge" style="align-self:flex-start">PRE-ORDER</span><span style="font-size:13px;color:var(--c-heading)">Ships <b>22 November</b></span><span class="meter" style="background:var(--c-surface)"><span style="width:64%;background:var(--c-outline)"></span></span></div>
        @break
    @case('sales-pop')
        <div class="peek" style="justify-content:flex-end;padding-bottom:20px" aria-hidden="true"><span class="mini" style="flex-direction:row;align-items:center;gap:10px;align-self:flex-start;padding:10px 14px;font-size:13px;box-shadow:var(--shadow)"><span style="width:32px;height:32px;border-radius:8px;background:var(--accent-soft)"></span><span><b>Someone in Canada</b> purchased<br><span style="color:var(--c-dim)">Glow Serum · 12 minutes ago</span></span></span></div>
        @break
    @case('trust-social-proof')
        <div class="peek" aria-hidden="true"><span class="tile-row">@foreach (['Free shipping', '30-day returns', 'Secure checkout'] as $t)<span class="mini" style="flex:1;padding:12px 6px;text-align:center;font-size:12px;font-weight:500">{{ $t }}</span>@endforeach</span></div>
        @break
    @default
        <div class="peek" aria-hidden="true"><span class="bar"></span><span class="bar on"></span></div>
@endswitch
