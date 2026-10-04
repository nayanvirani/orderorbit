<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Support\Attachment;
use App\Models\Support\Ticket;
use App\Services\Support\SupportDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Support (section 34) for merchants: open tickets, follow the conversation, reply and close.
 */
class SupportController extends Controller
{
    public function __construct(private readonly SupportDesk $desk) {}

    public function index(Request $request, Store $store): View
    {
        return view('app.support.index', [
            'store' => $store,
            'tickets' => Ticket::where('store_id', $store->id)->latest('updated_at')->get(),
            'errors' => session('support_errors', []),
        ]);
    }

    public function store(Request $request, Store $store): RedirectResponse|View
    {
        $validator = Validator::make($request->all(), [
            'subject' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'in:'.implode(',', array_keys(Ticket::CATEGORIES))], 'priority' => ['nullable', 'in:'.implode(',', array_keys(Ticket::PRIORITIES))],
        ] + SupportDesk::ATTACHMENT_RULES, ['attachments.*.mimes' => 'Attach images, PDFs, text or CSV files.', 'attachments.*.max' => 'Each attachment can be up to 4 MB.']);
        if ($validator->fails()) {
            return view('app.support.index', ['store' => $store, 'tickets' => Ticket::where('store_id', $store->id)->latest('updated_at')->get(), 'errors' => $validator->errors()->toArray(), 'old' => $request->only(['subject', 'body', 'category', 'priority'])]);
        }
        $ticket = $this->desk->open($store, $request->attributes->get('storeUser'), $validator->validated(), (array) $request->file('attachments', []));

        return redirect()->to(app_route('app.support.show', ['ticket' => $ticket->id, 'notice' => 'ticket_created']));
    }

    public function show(Request $request, Store $store, int $ticket): View
    {
        $ticket = $this->find($store, $ticket);

        return view('app.support.show', ['store' => $store, 'ticket' => $ticket, 'messages' => $ticket->messages()->where('internal', false)->with('attachments')->get()]);
    }

    public function reply(Request $request, Store $store, int $ticket): RedirectResponse
    {
        $ticket = $this->find($store, $ticket);
        $validator = Validator::make($request->all(), ['body' => ['required', 'string', 'max:10000']] + SupportDesk::ATTACHMENT_RULES);
        if ($validator->fails()) {
            return redirect()->to(app_route('app.support.show', ['ticket' => $ticket->id, 'error' => $validator->errors()->first()]));
        }
        $this->desk->reply($ticket, 'merchant', $request->input('body'), (array) $request->file('attachments', []), false, $request->attributes->get('storeUser'));

        return redirect()->to(app_route('app.support.show', ['ticket' => $ticket->id, 'notice' => 'reply_sent']));
    }

    public function close(Request $request, Store $store, int $ticket): RedirectResponse
    {
        $ticket = $this->find($store, $ticket);
        $ticket->forceFill(['status' => $ticket->status === 'closed' ? 'open' : 'closed', 'resolved_at' => $ticket->status === 'closed' ? null : ($ticket->resolved_at ?? now())])->save();

        return redirect()->to(app_route('app.support.show', ['ticket' => $ticket->id, 'notice' => 'saved']));
    }

    public function attachment(Request $request, Store $store, int $attachment): Response
    {
        $file = Attachment::with('message.ticket')->findOrFail($attachment);
        abort_unless($file->message->ticket->store_id === $store->id && ! $file->message->internal, 404);

        return self::download($file);
    }

    public static function download(Attachment $file): Response
    {
        $raw = $file->getRawOriginal('content');
        $content = base64_decode(is_resource($raw) ? stream_get_contents($raw) : (string) $raw);

        return response($content, 200, [
            'Content-Type' => $file->mime,
            'Content-Disposition' => (str_starts_with($file->mime, 'image/') || $file->mime === 'application/pdf' ? 'inline' : 'attachment').'; filename="'.addslashes($file->filename).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function find(Store $store, int $id): Ticket
    {
        return Ticket::where('store_id', $store->id)->findOrFail($id);
    }
}
