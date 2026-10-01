<x-browser url="glowlab.co/collections/skincare" aria-label="Sales pop notification on a collection page">
    <div class="mk-sp-page">
        <div class="mk-sp-grid">
            @foreach (['b', 'c', 'd', 'e', 'b', 'c'] as $art)
                <div><div class="ph art {{ $art }}"></div><div class="line w80"></div><div class="line w40"></div></div>
            @endforeach
        </div>
        <div class="mk-sp-toast">
            <div class="ph art b"></div>
            <div>
                <b>Someone in Canada</b>
                <span>purchased <strong>Glow Serum</strong></span>
                <small>12 minutes ago · <em><x-icon name="check"/> Verified purchase</em></small>
            </div>
            <i aria-hidden="true">×</i>
        </div>
    </div>
    <x-slot:chips>
        <div class="float-chip c3" style="left:auto;right:6%"><x-icon name="bell"/> From real orders — never invented</div>
    </x-slot:chips>
</x-browser>
