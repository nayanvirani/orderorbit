{{-- Compact template preview. $type: bundle, gift, shipping, qty, upsell, countdown, sticky, trust, promo, thankyou, account, flow; $v: variant index --}}
@php($v = ($v ?? 0) % 5)
@switch($type)
    @case('bundle')
        <div class="oo-block" style="padding:10px">
            <div class="bundle-items" style="grid-template-columns:repeat({{ 3 + $v % 2 }},1fr)">
                @for ($i = 0; $i < 3 + $v % 2; $i++)<div class="bundle-item {{ $i < 2 ? 'sel' : '' }}"><div class="ph art {{ ['b','c','d','e'][($i + $v) % 4] }}"></div></div>@endfor
            </div>
            <div class="progress" style="margin-top:8px"><i style="width:{{ 50 + $v * 8 }}%"></i></div>
        </div>
        @break
    @case('gift')
        <div class="oo-block" style="padding:12px">
            <div style="font:800 11px var(--font-head)">🎁 {{ $v % 2 ? 'Gift unlocked!' : 'Spend $20 more for a free gift' }}</div>
            <div class="ladder" style="margin:14px 2px 4px"><div class="progress"><i style="width:{{ 55 + $v * 7 }}%"></i></div><span class="ms done" style="left:50%;width:22px;height:22px;top:-6px;font-size:10px">🎁</span><span class="ms" style="left:100%;width:22px;height:22px;top:-6px;font-size:10px">✨</span></div>
        </div>
        @break
    @case('shipping')
        <div class="ship-bar" style="{{ $v % 2 ? 'background:#fff;color:#1d1b33;border:1px solid #ebe9f5' : '' }}"><div class="row"><span>🚚 ${{ 12 + $v * 3 }} to free shipping</span></div><div class="progress"><i style="width:{{ 62 + $v * 6 }}%"></i></div></div>
        @break
    @case('qty')
        <div class="tiers">
            <div class="tier" style="padding:7px 10px"><span class="radio"></span><b>Buy 1</b><div class="save"><b>$29</b></div></div>
            <div class="tier sel" style="padding:7px 10px"><span class="radio"></span><b>Buy {{ 2 + $v % 2 }}</b><div class="save"><b>$26 ea</b><em>Save {{ 10 + $v * 3 }}%</em></div></div>
        </div>
        @break
    @case('upsell')
        <div class="oo-block" style="padding:10px"><div class="upsell"><div class="ph art {{ ['b','c','d','e'][$v % 4] }}" style="width:44px;height:44px"></div><div class="meta"><b>Add the matching item</b><em>Save 10%</em></div><span class="add-btn">+ Add</span></div></div>
        @break
    @case('countdown')
        <div class="sale-banner" style="padding:10px 12px"><div><strong style="font-size:12px">Sale ends in</strong></div><div class="timer"><div style="width:34px"><b style="font-size:13px">02</b></div><div style="width:34px"><b style="font-size:13px">14</b></div><div style="width:34px"><b style="font-size:13px">09</b></div></div></div>
        @break
    @case('sticky')
        <div class="sticky-bar oo-block" style="position:static;animation:none;padding:8px"><div class="ph art c" style="width:30px;height:30px"></div><div class="meta"><b>Product name</b>$29.00</div><span class="add-btn">Add to cart</span></div>
        @break
    @case('trust')
        @if ($v % 2)
            <div class="trust-row"><div><x-icon name="truck"/>Free shipping</div><div><x-icon name="repeat"/>Returns</div><div><x-icon name="lock"/>Secure</div></div>
        @else
            <div class="review"><div class="stars">★★★★★</div><p>"Absolutely love it — ordering again."</p><div class="who"><span class="avatar {{ ['','b','c'][$v % 3] }}">AK</span>Verified buyer</div></div>
        @endif
        @break
    @case('promo')
        <div class="oo-block" style="padding:12px;background-image:linear-gradient(120deg,#f3f1ff,#ffeaf3),var(--grad)"><div style="font:800 12px var(--font-head)">✨ Members get 15% off today</div><div class="line w70" style="margin-top:8px;background:#fff"></div></div>
        @break
    @case('thankyou')
        <div class="ty-head" style="margin-bottom:8px"><span class="ok" style="width:26px;height:26px"><x-icon name="check"/></span><div><b style="font-size:12px">Thank you, Jamie!</b><span>Order #1051 confirmed</span></div></div>
        <div class="oo-block" style="padding:8px"><div class="upsell"><div class="ph art b" style="width:32px;height:32px"></div><div class="meta"><b style="font-size:11px">10% off your next order</b></div><span class="add-btn">Claim</span></div></div>
        @break
    @case('account')
        <div class="order-row"><div class="ph art c" style="width:32px;height:32px"></div><div class="meta"><b>Cold Brew × 3</b><span>Delivered</span></div><span class="add-btn">Reorder</span></div>
        @break
    @case('flow')
        <div style="display:flex;flex-direction:column;align-items:center;gap:6px;transform:scale(.92)">
            <div class="wf-node trigger" style="width:190px;padding:7px 10px"><span class="ic" style="width:24px;height:24px"><x-icon name="zap"/></span><b>Trigger</b></div>
            <div class="wf-node wait" style="width:190px;padding:7px 10px"><span class="ic" style="width:24px;height:24px"><x-icon name="clock"/></span><b>Wait</b></div>
            <div class="wf-node action" style="width:190px;padding:7px 10px"><span class="ic" style="width:24px;height:24px"><x-icon name="mail"/></span><b>Send email</b></div>
        </div>
        @break
@endswitch
