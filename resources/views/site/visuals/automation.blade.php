<x-browser url="admin.shopify.com · OrderOrbit Space › Workflows › Review Request" :flush="true" aria-label="Automation workflow canvas">
    <div class="canvas">
        <div class="wf-node trigger"><span class="ic"><x-icon name="zap"/></span><div><small>Trigger</small><b>Order delivered</b></div></div>
        <div class="wf-link"></div>
        <div class="wf-node wait"><span class="ic"><x-icon name="clock"/></span><div><small>Wait</small><b>5 days</b></div></div>
        <div class="wf-link"></div>
        <div class="wf-node action"><span class="ic"><x-icon name="mail"/></span><div><small>Action</small><b>Email review request</b></div></div>
        <div class="wf-link"></div>
        <div class="wf-node cond"><span class="ic"><x-icon name="split"/></span><div><small>If / else · after 7 days</small><b>Review submitted?</b></div></div>
        <div class="wf-branch">
            <div><div class="lbl">YES</div><div class="wf-node action"><span class="ic"><x-icon name="user"/></span><div><small>Action</small><b>Tag: reviewer</b></div></div></div>
            <div><div class="lbl">NO</div><div class="wf-node action"><span class="ic"><x-icon name="mail"/></span><div><small>Action</small><b>Send reminder</b></div></div></div>
        </div>
        <div class="run-status"><span class="pill green">● Enabled</span><span class="pill gray">v3 · Published</span><span class="pill">1,284 runs · 99.4% success</span></div>
    </div>
    <x-slot:chips>
        <div class="float-chip c1"><x-icon name="mail" style="color:#8f5cff"/> Email sending included</div>
    </x-slot:chips>
</x-browser>
