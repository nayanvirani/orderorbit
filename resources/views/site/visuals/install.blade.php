<x-browser url="admin.shopify.com · Install app" aria-label="Installing Growvia from Shopify">
    <div style="display:flex;gap:14px;align-items:center">
        <div style="width:56px;height:56px;border-radius:16px;background:#0e0b2b;display:grid;place-items:center"><svg style="width:36px;height:36px"><use href="#i-growvia"></use></svg></div>
        <div><b style="font:800 16px var(--font-head)">Install Growvia</b><div style="color:#6b6889;font-size:12px">Shopify CRO, Checkout &amp; Customer Experience</div></div>
    </div>
    <div class="oo-block" style="margin-top:16px">
        <span class="oo-tag">LEAST-PRIVILEGE</span>
        <h5>Growvia will be able to:</h5>
        @foreach (['View products and themes', 'Create discounts for your offers', 'Measure results with consent-aware analytics', 'Bill through your Shopify invoice'] as $perm)
            <div style="display:flex;gap:8px;align-items:center;font-size:12px;font-weight:600;margin-top:8px"><x-icon name="check-circle" style="width:16px;height:16px;color:#12b886"/>{{ $perm }}</div>
        @endforeach
    </div>
    <div style="display:flex;gap:8px;margin-top:16px"><div class="fake-btn light" style="flex:1">Cancel</div><div class="fake-btn" style="flex:2;background:#0b8a63">Install app</div></div>
</x-browser>
