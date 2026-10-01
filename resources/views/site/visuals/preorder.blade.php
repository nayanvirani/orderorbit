<x-browser url="northtrail.co/products/storm-shell-jacket" aria-label="Pre-order widget on a product page">
    <div class="pdp">
        <div class="gallery art c"><div class="tee"></div></div>
        <div>
            <h4>Storm Shell Jacket</h4>
            <div class="price">$148.00</div>
            <div style="display:flex;gap:6px;margin:10px 0">@foreach (['S','M','L','XL'] as $sz)<span class="chip gray">{{ $sz }}</span>@endforeach</div>
            <div class="mk-po">
                <div class="mk-po-head"><span class="mk-po-badge">Pre-order</span><span><x-icon name="calendar"/> 22 November</span></div>
                <div class="mk-po-tiles"><div><b>1</b><small>MONTH</small></div><div><b>3</b><small>WEEKS</small></div><div><b>2</b><small>DAYS</small></div></div>
                <div class="mk-po-bar"><span>Production progress</span><b>64%</b></div>
                <div class="progress"><i style="width:64%"></i></div>
            </div>
            <div class="fake-btn">Pre-order now</div>
        </div>
    </div>
    <x-slot:chips>
        <div class="float-chip c3"><x-icon name="check"/> Order line: “Pre-order: Ships by 22 Nov”</div>
    </x-slot:chips>
</x-browser>
