<x-browser url="northtrail.co/pages/summer-sale" aria-label="Countdown banner and product timer">
    <div class="oo-block" style="padding:0;margin-bottom:14px">
        <span class="oo-tag">GROWVIA COUNTDOWN</span>
        <div class="sale-banner">
            <div><strong>Summer Sale · up to 30% off</strong><span>Ends Sunday 11:59 PM (your timezone)</span></div>
            <div class="timer" data-countdown>
                <div><b>02</b><small>DAYS</small></div><div><b>14</b><small>HRS</small></div><div><b>09</b><small>MIN</small></div><div><b>41</b><small>SEC</small></div>
            </div>
        </div>
    </div>
    <div class="pdp">
        <div class="gallery art b"><div class="tee"></div></div>
        <div>
            <h4>Trail Tee — Organic</h4>
            <div class="price">$28.00 <s>$40.00</s></div>
            <div class="line w90"></div><div class="line w80"></div><div class="line w60"></div>
            <div style="display:flex;gap:6px;margin:12px 0">@foreach (['S','M','L','XL'] as $sz)<span class="chip gray">{{ $sz }}</span>@endforeach</div>
            <div class="fake-btn">Add to cart</div>
        </div>
    </div>
    <x-slot:chips>
        <div class="float-chip c3"><x-icon name="clock" style="color:#8f5cff"/> Real deadline — never resets</div>
    </x-slot:chips>
</x-browser>
