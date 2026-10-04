<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Shopify\Files;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Image uploads from the builder, saved to the store's Shopify Files.
 */
class UploadController extends Controller
{
    public function image(Request $request, Store $store, Files $files): JsonResponse
    {
        if (! $store->hasScope('write_files')) {
            return response()->json(['message' => 'Uploading needs a new permission. Reload the app and approve it, or paste an image link instead.'], 403);
        }

        $validator = Validator::make($request->all(), ['image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120']], [
            'image.required' => 'Choose an image to upload.',
            'image.mimes' => 'Use a JPG, PNG, GIF or WebP image.',
            'image.max' => 'That image is over 5 MB.',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            return response()->json(['url' => $files->uploadImage($store, $request->file('image'), (string) $request->input('alt', ''))]);
        } catch (RuntimeException $e) {
            Log::warning('Image upload failed', ['store' => $store->shop_domain, 'error' => $e->getMessage()]);

            return response()->json(['message' => str_starts_with($e->getMessage(), 'Admin API') ? 'Shopify could not take the upload right now. Try again.' : $e->getMessage()], 422);
        }
    }
}
