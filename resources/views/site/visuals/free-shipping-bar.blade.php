<x-browser url="northtrail.co/collections/all" aria-label="Free shipping progress bar">
    <div class="oo-block" style="padding:0;margin-bottom:14px">
        <span class="oo-tag">ORDERORBIT SHIPPING BAR</span>
        <div class="ship-bar">
            <div class="row"><span>🚚 You're <b>$12</b> away from free shipping</span><span style="opacity:.7">$60</span></div>
            <div class="progress anim"><i style="width:80%"></i></div>
        </div>
    </div>
    <div class="store-nav"><b>NORTH TRAIL</b><span>Men · Women · Gear · Cart (2)</span></div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
        @foreach ([['art b', 'Trail Tee'], ['art c', 'Camp Mug'], ['art d', 'Day Pack'], ['art e', 'Wool Socks'], ['art', 'Rain Shell'], ['art c', 'Beanie']] as [$cls, $name])
            <div><div class="{{ $cls }}" style="aspect-ratio:1;border-radius:12px"><div class="tee {{ $loop->even ? 'pink' : '' }}" style="width:48%;height:48%"></div></div><div style="font:700 12px/1.3 var(--font-head);margin-top:6px">{{ $name }}</div><div style="font-size:11px;color:#6b6889">${{ 18 + $loop->index * 7 }}.00</div></div>
        @endforeach
    </div>
    <x-slot:chips>
        <div class="float-chip c2"><span class="dot"></span><code>orderorbit:shipping_threshold_reached</code></div>
    </x-slot:chips>
</x-browser>
