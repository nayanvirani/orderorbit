<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Ai\Claude;
use App\Services\Ai\OfferIdeas;
use App\Support\Features;
use App\Support\Plans;
use App\Support\Spa\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Offers › Offer ideas (G20): products bought together, and AI offer text on request. */
class OfferIdeasController extends Controller
{
    public function __construct(private readonly OfferIdeas $ideas) {}

    public function show(Store $store, Claude $claude): Page
    {
        abort_unless(Features::enabled('ai_offer_ideas', $store), 404);

        return page('cro/ideas', [
            'pairs' => $store->planIncludes('ai_offer_ideas') ? $this->ideas->pairs($store) : [],
            'minOrders' => OfferIdeas::MIN_ORDERS, 'ai' => $claude->available($store), 'configured' => $claude->configured(),
            'locked' => $store->planIncludes('ai_offer_ideas') ? null : Plans::unlockMessage('ai_offer_ideas', 'get offer ideas', $store),
        ]);
    }

    /** Three text options for an offer (the merchant picks and edits; nothing is saved here). */
    public function copy(Request $request, Store $store, Claude $claude): JsonResponse
    {
        abort_unless(OfferIdeas::available($store), 404);
        $data = $request->validate(['type' => ['nullable', 'string', 'max:40'], 'products' => ['array', 'max:6'], 'products.*' => ['string', 'max:120'],
            'discount' => ['nullable', 'string', 'max:40'], 'headline' => ['nullable', 'string', 'max:200'], 'subheadline' => ['nullable', 'string', 'max:300'], 'cta' => ['nullable', 'string', 'max:60']]);
        if (! $claude->available($store)) {
            return response()->json(['message' => $claude->configured() ? 'This month\'s AI budget is used up. AI starts again on the 1st.' : 'AI isn\'t switched on for Growvia yet.'], 402);
        }
        $options = $this->ideas->copy($store, array_filter($data));

        return $options ? response()->json(['options' => $options]) : response()->json(['message' => 'No suggestions this time. Try again.'], 422);
    }
}
