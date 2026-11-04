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

    /** The signed "Turn off weekly email" link in the Monday summary (G16), per person. */
    public function weeklyOff(Request $request, int $user): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        \App\Models\StoreUser::whereKey($user)->update(['weekly_email' => false]);

        return response()->view('documents.message', ['title' => 'Weekly email turned off', 'text' => 'You won\'t get the Monday summary for this store any more. Turn it back on in Growvia under Settings › Team and permissions.']);
    }
}
