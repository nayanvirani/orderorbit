<x-browser url="account.brewhaus.com/orders" :flush="true" aria-label="Customer account with reorder and rewards">
    <div class="acct">
        <div class="store-nav" style="margin-bottom:6px"><b>BREWHAUS</b><span>Orders · Profile · <b style="color:#1d1b33;font-size:12px">Hi, Jamie</b></span></div>
        <div class="acct-grid">
            <div>
                <div class="oo-block">
                    <span class="oo-tag">REORDER</span>
                    <h5>My Orders</h5>
                    <div class="order-row"><div class="ph art c"></div><div class="meta"><b>Cold Brew × 3</b><span>#1042 · Delivered 21 days ago</span></div><span class="add-btn">Reorder</span></div>
                    <div class="order-row"><div class="ph art b"></div><div class="meta"><b>Pour-over Kit</b><span>#1017 · Delivered</span></div><span class="chip gray">Review</span></div>
                </div>
                <div class="order-row" style="margin-top:10px"><x-icon name="truck" style="color:#8f5cff"/><div class="meta"><b>Track Order #1051</b><span>Out for delivery · arrives today</span></div></div>
            </div>
            <div>
                <div class="rewards"><span style="opacity:.8">My Rewards</span><b>1,240 pts</b><span style="opacity:.8">260 pts to a free bag</span><div class="progress" style="margin-top:8px;background:rgba(255,255,255,.2)"><i style="width:82%"></i></div></div>
                <div class="review" style="margin-top:10px"><b style="font:700 12px var(--font-head)">My Reviews</b><p>Rate your Pour-over Kit</p><div class="stars" style="font-size:16px;margin-top:4px">☆☆☆☆☆</div></div>
            </div>
        </div>
    </div>
    <x-slot:chips>
        <div class="float-chip c2"><x-icon name="repeat" style="color:#8f5cff"/> One-click reorder</div>
    </x-slot:chips>
</x-browser>
