<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Ai\Ai;
use App\Services\Ai\OfferIdeas;
use App\Support\Features;
use App\Support\Plans;
use App\Support\Spa\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Offers › Offer ideas (G20): products bought together, and offer text on request (Growvia's own, or AI when it's on). */
class OfferIdeasController extends Controller
{
    public function __construct(private readonly OfferIdeas $ideas) {}

    public function show(Store $store, Ai $ai): Page
    {
        abort_unless(Features::enabled('ai_offer_ideas', $store), 404);

        return page('cro/ideas', [
            'pairs' => $store->planIncludes('ai_offer_ideas') ? $this->ideas->pairs($store) : [],
            'minOrders' => OfferIdeas::MIN_ORDERS, 'ai' => $ai->available($store),
            'locked' => $store->planIncludes('ai_offer_ideas') ? null : Plans::unlockMessage('ai_offer_ideas', 'get offer ideas', $store),
        ]);
    }

    /** Three text options for an offer (the merchant picks and edits; nothing is saved here). */
    public function copy(Request $request, Store $store): JsonResponse
    {
        abort_unless(OfferIdeas::available($store), 404);
        $data = $request->validate(['type' => ['nullable', 'string', 'max:40'], 'products' => ['array', 'max:6'], 'products.*' => ['string', 'max:120'],
            'discount' => ['nullable', 'string', 'max:40'], 'headline' => ['nullable', 'string', 'max:200'], 'subheadline' => ['nullable', 'string', 'max:300'], 'cta' => ['nullable', 'string', 'max:60']]);

        return response()->json($this->ideas->suggest($store, array_filter($data)));
    }
}
