<x-browser url="admin.shopify.com · OrderOrbit › Analytics" :flush="true" aria-label="Analytics dashboard">
    <div class="dash">
        <div class="side">
            <div><x-icon name="layout"/>Dashboard</div><div class="on"><x-icon name="chart"/>Analytics</div><div><x-icon name="sparkle"/>Experiences</div><div><x-icon name="split"/>Experiments</div><div><x-icon name="flow"/>Automation</div><div><x-icon name="target"/>Audiences</div>
        </div>
        <div class="main">
            <div class="kpis">
                <div class="kpi"><small>Revenue influenced</small><b>$212.9k</b><em>▲ 14%</em></div>
                <div class="kpi"><small>Conversion rate</small><b>3.1%</b><em>▲ 0.4</em></div>
                <div class="kpi"><small>AOV</small><b>$68.40</b><em>▲ 6%</em></div>
                <div class="kpi"><small>CRO revenue</small><b>$58.2k</b><em>▲ 11%</em></div>
            </div>
            <div class="chart-box">
                <div class="t">Revenue by experience <span>Last 30 days</span></div>
                <svg viewBox="0 0 300 80" preserveAspectRatio="none" style="width:100%;height:80px">
                    <defs><linearGradient id="area" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#8f5cff" stop-opacity=".35"/><stop offset="1" stop-color="#8f5cff" stop-opacity="0"/></linearGradient></defs>
                    <path d="M0 62 C 25 58, 40 50, 60 52 S 100 38, 120 40 S 160 26, 180 30 S 220 18, 240 20 S 280 8, 300 10 L300 80 L0 80 Z" fill="url(#area)"/>
                    <path d="M0 62 C 25 58, 40 50, 60 52 S 100 38, 120 40 S 160 26, 180 30 S 220 18, 240 20 S 280 8, 300 10" fill="none" stroke="#7a5cff" stroke-width="2.5"/>
                    <path d="M0 70 C 30 68, 60 64, 90 62 S 150 56, 180 54 S 240 48, 300 44" fill="none" stroke="#ff5ca8" stroke-width="2" stroke-dasharray="4 4"/>
                </svg>
            </div>
            <div class="chart-box">
                <div class="t">Bundle funnel <span>Drop-off per step</span></div>
                <div class="funnel">
                    <div><span>Product view</span><s><i style="width:100%"></i></s><em>100%</em></div>
                    <div><span>Bundle view</span><s><i style="width:74%"></i></s><em>74%</em></div>
                    <div><span>Interaction</span><s><i style="width:41%"></i></s><em>41%</em></div>
                    <div><span>Add to cart</span><s><i style="width:22%"></i></s><em>22%</em></div>
                    <div><span>Purchase</span><s><i style="width:11%"></i></s><em>11%</em></div>
                </div>
            </div>
        </div>
    </div>
    <x-slot:chips>
        <div class="float-chip c1"><x-icon name="shield" style="color:#12b886"/> Consent-aware Web Pixel</div>
    </x-slot:chips>
</x-browser>
