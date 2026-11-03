<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Support\Ticket;
use App\Services\Support\LiveChat;
use App\Services\Support\SupportDesk;
use App\Support\Features;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Live chat (G43) for store owners: the chat panel polls show() and posts here. */
class ChatController extends Controller
{
    public function __construct(private readonly LiveChat $chat) {}

    public function show(Request $request, Store $store): JsonResponse
    {
        $this->enabled($store);

        return response()->json($this->chat->forMerchant($store, $request->attributes->get('storeUser'), $request->boolean('open')));
    }

    public function send(Request $request, Store $store): JsonResponse
    {
        $this->enabled($store);
        $request->validate([
            'body' => ['required', 'string', 'max:5000'], 'topic' => ['nullable', 'string', 'max:20'], 'page' => ['nullable', 'string', 'max:255'],
        ] + SupportDesk::ATTACHMENT_RULES, ['attachments.*.mimes' => 'Attach images, PDFs, text or CSV files.', 'attachments.*.max' => 'Each attachment can be up to 4 MB.']);
        $user = $request->attributes->get('storeUser');
        $this->chat->fromMerchant($store, $user, $request->input('body'), $request->input('topic'), $request->input('page'), (array) $request->file('attachments', []));

        return response()->json($this->chat->forMerchant($store, $user, true));
    }

    public function typing(Request $request, Store $store, int $chat): JsonResponse
    {
        $this->chat->typing($this->find($request, $store, $chat), 'merchant');

        return response()->json(['ok' => true]);
    }

    public function rate(Request $request, Store $store, int $chat): JsonResponse
    {
        $request->validate(['rating' => ['required', 'integer', 'in:1,-1']]);
        $this->chat->rate($this->find($request, $store, $chat), (int) $request->input('rating'));

        return response()->json($this->chat->forMerchant($store, $request->attributes->get('storeUser'), true));
    }

    public function close(Request $request, Store $store, int $chat): JsonResponse
    {
        $this->chat->close($this->find($request, $store, $chat));

        return response()->json($this->chat->forMerchant($store, $request->attributes->get('storeUser'), true));
    }

    private function enabled(Store $store): void
    {
        abort_unless(Features::enabled('live_chat', $store), 404);
    }

    private function find(Request $request, Store $store, int $id): Ticket
    {
        $this->enabled($store);

        return Ticket::where('store_id', $store->id)->where('channel', 'chat')->where('store_user_id', $request->attributes->get('storeUser')?->id)->findOrFail($id);
    }
}
