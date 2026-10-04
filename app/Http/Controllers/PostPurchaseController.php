<?php

namespace App\Http\Controllers;

use App\Services\Checkout\PostPurchase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Called by the orderorbit-post-purchase extension, with the token Shopify signs for each purchase.
 */
class PostPurchaseController extends Controller
{
    public function __construct(private readonly PostPurchase $funnels) {}

    /** Which offer to show (or none). */
    public function offer(Request $request): JsonResponse
    {
        try {
            [$store, $facts] = $this->funnels->context((string) $request->input('token'), (string) $request->input('shop'), $this->sent($request));
            $funnel = $this->funnels->funnel($store, $facts['products'], $facts['total']);
            if ($funnel) {
                $this->funnels->record($store, $funnel['experience_id'], 'view');
            }

            return $this->json(['funnel' => $funnel]);
        } catch (ModelNotFoundException|\RuntimeException) {
            return $this->json(['funnel' => null]);
        } catch (Throwable $e) {
            report($e);

            return $this->json(['funnel' => null]);
        }
    }

    /** Signs the change that adds the accepted offer to the order. */
    public function sign(Request $request): JsonResponse
    {
        try {
            [$store, $facts] = $this->funnels->context((string) $request->input('token'), (string) $request->input('shop'), $this->sent($request));
            $token = $this->funnels->sign($store, $facts['reference_id'], (string) $request->input('experience_id'), (int) $request->input('step'), $facts['products'], $facts['total']);

            return $this->json(['token' => $token]);
        } catch (ModelNotFoundException|\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    /** A declined offer, for the funnel's numbers. */
    public function decline(Request $request): JsonResponse
    {
        try {
            [$store] = $this->funnels->context((string) $request->input('token'), (string) $request->input('shop'), $this->sent($request));
            $this->funnels->record($store, (string) $request->input('experience_id'), 'decline');
        } catch (Throwable) {
            // Analytics only.
        }

        return $this->json(['ok' => true]);
    }

    /** What the extension sent, used only when Shopify's token doesn't carry the purchase. */
    private function sent(Request $request): array
    {
        return [
            'products' => array_values(array_filter(array_map(fn ($id) => preg_match('/(\d+)$/', (string) $id, $m) ? $m[1] : null, (array) $request->input('products', [])))),
            'total' => (float) $request->input('total', 0),
            'reference_id' => (string) $request->input('reference_id', ''),
        ];
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Access-Control-Allow-Origin', '*');
    }
}
