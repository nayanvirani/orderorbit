<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The signed "turn these emails off" link in lifecycle emails (G24). */
class LifecycleController extends Controller
{
    public function unsubscribe(Request $request, int $store): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        Store::whereKey($store)->update(['lifecycle_unsubscribed' => true]);

        return response()->view('documents.message', ['title' => 'You won\'t get these emails again', 'text' => 'Growvia\'s tips and monthly results are turned off for this store. Important account and billing emails still arrive.']);
    }
}
