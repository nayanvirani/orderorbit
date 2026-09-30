<x-browser url="admin.shopify.com · OrderOrbit Space › Audiences › Rules" :flush="true" aria-label="Personalization rule builder">
    <div class="rules">
        <div style="display:flex;justify-content:space-between;align-items:center"><b style="font:800 14px var(--font-head)">Premium upsell for returning shoppers</b><span class="pill green">● Live</span></div>
        <div class="rule"><span class="k">IF</span><span class="chip"><x-icon name="user" style="width:13px;height:13px"/> Customer is returning</span></div>
        <div class="rule"><span class="k">AND</span><span class="chip">Cart value</span><span class="chip gray">is over</span><span class="chip">$75</span></div>
        <div class="rule"><span class="k">THEN</span><span class="chip pink"><x-icon name="sparkle" style="width:13px;height:13px"/> Show "Premium Offer" upsell</span></div>
        <div style="font:700 11px var(--font-head);color:#6b6889;margin-top:4px">SEGMENTS</div>
        <div class="segments">
            <span class="chip">New customers · 4,210</span><span class="chip">Returning · 2,980</span><span class="chip">VIP (3+ orders) · 412</span><span class="chip green">High AOV · 860</span><span class="chip gray">Mobile shoppers</span><span class="chip gray">Product X purchasers</span>
        </div>
        <div class="oo-block" style="margin-top:6px">
            <span class="oo-tag">WHAT A VIP SEES</span>
            <div class="upsell"><div class="ph art e"><div class="cam" style="width:70%"></div></div><div class="meta"><b>Pro Lens Kit — VIP price</b><em>Members save 20%</em></div><span class="add-btn">+ Add</span></div>
        </div>
    </div>
</x-browser>
