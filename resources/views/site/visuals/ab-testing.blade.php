<x-browser url="admin.shopify.com · OrderOrbit Space › Experiments › Bundle layout" :flush="true" aria-label="A/B test results">
    <div class="ab">
        <div class="ab-head"><div><b>Bundle layout: Tier Cards vs Radio Selector</b><div style="font-size:11px;color:#6b6889">Primary metric: revenue per visitor · Guardrail: cart abandonment</div></div><span class="pill green">● Winner found</span></div>
        <div style="font-size:10.5px;font-weight:700;color:#6b6889">Traffic allocation 50 / 50</div>
        <div class="traffic"><i></i><i></i></div>
        <div class="variants" style="margin-top:12px">
            <div class="variant"><div class="vh">A · Radio Selector <span class="pill gray">Control</span></div><div class="big">$2.04</div><div style="color:#6b6889">rev / visitor · CVR 3.0%</div><div class="bar"><i style="width:72%"></i></div></div>
            <div class="variant win"><div class="vh">B · Tier Cards <span class="pill green">+14.2%</span></div><div class="big">$2.33</div><div style="color:#6b6889">rev / visitor · CVR 3.6%</div><div class="bar"><i style="width:86%"></i></div></div>
        </div>
        <div class="criteria">
            <div><x-icon name="check-circle"/>95% confidence reached</div>
            <div><x-icon name="check-circle"/>14 days (min 7)</div>
            <div><x-icon name="check-circle"/>6,420 visitors / variant</div>
            <div><x-icon name="check-circle"/>231 conversions / variant</div>
        </div>
        <div class="fake-btn grad" style="margin-top:14px">Apply winner</div>
    </div>
    <x-slot:chips>
        <div class="float-chip c3"><x-icon name="split" style="color:#8f5cff"/> Stable visitor assignment</div>
    </x-slot:chips>
</x-browser>
