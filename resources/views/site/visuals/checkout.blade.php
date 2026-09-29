<x-browser url="checkout.northtrail.co" :flush="true" aria-label="Checkout with trust and shipping blocks">
    <div class="checkout">
        <div class="left">
            <div class="store-nav" style="margin-bottom:8px"><b>NORTH TRAIL</b><span>Information › Shipping › Payment</span></div>
            <div class="co-label">Contact</div>
            <div class="field">Email</div>
            <div class="co-label">Delivery</div>
            <div class="field-row"><div class="field">First name</div><div class="field">Last name</div></div>
            <div class="field">Address</div>
            <div class="oo-block" style="margin-top:14px">
                <span class="oo-tag">CHECKOUT TRUST</span>
                <div class="trust-row">
                    <div><x-icon name="shield"/>2-year guarantee</div>
                    <div><x-icon name="repeat"/>Free returns</div>
                    <div><x-icon name="star"/>4.9 · 8k reviews</div>
                </div>
            </div>
            <div class="fake-btn" style="margin-top:14px">Continue to shipping</div>
        </div>
        <div class="right">
            <div class="summary-line"><div class="ph art b"></div><span>Rain Shell · M</span><b>$129</b></div>
            <div class="summary-line"><div class="ph art c"></div><span>Wool Socks × 2</span><b>$24</b></div>
            <div class="oo-block" style="margin-top:14px">
                <span class="oo-tag">SHIPPING PROGRESS</span>
                <div style="font-size:11px;font-weight:700">🎉 You've unlocked free express shipping</div>
                <div class="progress" style="margin-top:8px"><i style="width:100%"></i></div>
            </div>
            <div class="total"><span>Total</span><span>$153.00</span></div>
        </div>
    </div>
    <x-slot:chips>
        <div class="float-chip c1"><x-icon name="check-circle" style="color:#12b886"/> Only targets your plan supports</div>
        <div class="float-chip c3"><span class="dot"></span>Checkout block viewed</div>
    </x-slot:chips>
</x-browser>
