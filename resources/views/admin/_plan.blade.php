{{-- A store's plan as a badge, with complimentary plans and custom access marked. --}}
@php($plan = $store->effectivePlan())
@if ($plan)
    <span class="ad-badge {{ $store->compPlan() ? 'accent' : 'info' }}">{{ config('shopify.billing.plans.'.$plan.'.name', ucfirst($plan)) }}{{ $store->compPlan() ? ' · free' : '' }}{{ $store->isTestShop() && ! $store->plan ? ' · test' : '' }}</span>
@else
    <span class="ad-badge">No plan</span>
@endif
@if (! empty($store->entitlements['modules_on']) || ! empty($store->entitlements['modules_off']) || ! empty($store->entitlements['limits']) || array_key_exists('sales_limit', $store->entitlements ?? []))
    <span class="ad-badge accent" title="Custom modules or limits">Custom</span>
@endif
@if ($store->offersSuspended())<span class="ad-badge bad">Stopped</span>@elseif ($store->over_limit_since)<span class="ad-badge warn">Over limit</span>@endif
