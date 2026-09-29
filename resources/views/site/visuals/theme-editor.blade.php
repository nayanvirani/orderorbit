<x-browser url="admin.shopify.com · Online Store › Themes › Customize" :flush="true" aria-label="Adding an OrderOrbit block in the Theme Editor">
    <div style="display:grid;grid-template-columns:190px 1fr;min-height:300px">
        <div style="border-right:1px solid #ebe9f5;padding:12px;font-size:11.5px;background:#fbfaff">
            <b style="font:800 12px var(--font-head)">Product information</b>
            @foreach (['Title', 'Price', 'Variant picker'] as $b)<div style="padding:7px 8px;border-radius:7px;margin-top:6px;background:#fff;border:1px solid #ebe9f5">{{ $b }}</div>@endforeach
            <div class="oo-block" style="padding:8px;margin-top:8px"><span class="oo-tag">APP BLOCK</span><b style="font-size:11.5px">OrderOrbit · Bundle</b></div>
            @foreach (['Buy buttons', 'Description'] as $b)<div style="padding:7px 8px;border-radius:7px;margin-top:6px;background:#fff;border:1px solid #ebe9f5">{{ $b }}</div>@endforeach
            <div style="margin-top:10px;color:#7a5cff;font-weight:700">+ Add block</div>
        </div>
        <div style="padding:14px">
            <div class="pdp" style="grid-template-columns:1fr 1fr">
                <div class="gallery art"><div class="bottle"></div></div>
                <div>
                    <h4>The Daily Routine</h4>
                    <div class="price">$64.00</div>
                    <div class="oo-block" style="padding:8px"><div class="bundle-items" style="grid-template-columns:repeat(3,1fr)"><div class="bundle-item sel"><div class="ph art b"></div></div><div class="bundle-item sel"><div class="ph art c"></div></div><div class="bundle-item"><div class="ph art d"></div></div></div></div>
                    <div class="fake-btn" style="margin-top:10px">Add to cart</div>
                </div>
            </div>
        </div>
    </div>
    <x-slot:chips>
        <div class="float-chip c3"><x-icon name="code-off" style="color:#8f5cff"/> No code — drag, drop, publish</div>
    </x-slot:chips>
</x-browser>
