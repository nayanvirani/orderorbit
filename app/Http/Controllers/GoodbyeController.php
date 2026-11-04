<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** The optional "why did you leave?" question from the goodbye email (G27 usage insights). */
class GoodbyeController extends Controller
{
    public const REASONS = [
        'sale_only' => 'I only needed it for a sale', 'hard_setup' => 'It was too hard to set up', 'missing_feature' => 'A feature I need is missing',
        'price' => 'It costs too much', 'other_app' => 'I switched to another app', 'not_working' => 'Something didn\'t work', 'other' => 'Something else',
    ];

    public function show(Request $request, int $store): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        $name = Store::whereKey($store)->value('name');
        abort_if($name === null && ! Store::whereKey($store)->exists(), 404);

        return response()->view('documents.goodbye', ['action' => $request->fullUrl(), 'reasons' => self::REASONS, 'store' => $name, 'sent' => false]);
    }

    public function store(Request $request, int $store): Response|RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $data = $request->validate(['reason' => ['required', 'in:'.implode(',', array_keys(self::REASONS))], 'note' => ['nullable', 'string', 'max:2000']]);
        // One answer per store; a second visit updates it.
        DB::table('uninstall_feedback')->updateOrInsert(['store_id' => $store], ['reason' => $data['reason'], 'note' => $data['note'] ?? null, 'updated_at' => now(), 'created_at' => now()]);

        return response()->view('documents.goodbye', ['action' => null, 'reasons' => self::REASONS, 'store' => null, 'sent' => true]);
    }
}
