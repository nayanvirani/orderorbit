<x-browser url="admin.shopify.com · Growvia › Welcome" aria-label="Choosing a goal during onboarding">
    <div style="display:flex;gap:6px;margin-bottom:14px">@for ($i = 0; $i < 8; $i++)<i style="flex:1;height:5px;border-radius:4px;background:{{ $i < 2 ? '#7a5cff' : '#efedf7' }}"></i>@endfor</div>
    <b style="font:800 16px var(--font-head)">What do you want to improve first?</b>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px">
        @foreach ([['sparkle', 'Conversion', false], ['bundle', 'Order value', true], ['repeat', 'Repeat purchase', false], ['card', 'Checkout', false]] as [$icon, $label, $on])
            <div class="{{ $on ? 'oo-block' : '' }}" style="{{ $on ? '' : 'border:1.5px solid #ebe9f5;border-radius:14px;' }}padding:14px">
                @if ($on)<span class="oo-tag">SELECTED</span>@endif
                <x-icon :name="$icon" style="color:#7a5cff"/>
                <b style="display:block;font:800 13px var(--font-head);margin-top:8px">{{ $label }}</b>
                <div class="line w80" style="margin-top:6px"></div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:14px;font-size:12px;color:#6b6889">Recommended for order value: <span class="chip">Bundles</span> <span class="chip">Progressive Gifts</span> <span class="chip">Free Gift</span></div>
</x-browser>
