<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactSubmission;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Contact inbox (G25): messages from the website contact form, read, replied to and closed here. */
class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), ContactSubmission::STATUSES) ? $request->query('status') : ($request->query('status') === 'all' ? 'all' : 'new');
        $q = trim((string) $request->query('q', ''));

        return view('admin.contact.index', [
            'status' => $status, 'q' => $q,
            'counts' => ContactSubmission::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'messages' => ContactSubmission::with('store:id,shop_domain')
                ->when($status !== 'all', fn ($w) => $w->where('status', $status))
                ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('store_url', 'like', "%{$q}%")->orWhere('message', 'like', "%{$q}%")))
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function show(int $message): View
    {
        $m = ContactSubmission::with(['store', 'replier'])->findOrFail($message);
        if ($m->status === 'new') {
            $m->forceFill(['status' => 'read', 'read_at' => now()])->save();
        }

        return view('admin.contact.show', [
            'm' => $m,
            'earlier' => ContactSubmission::where('email', $m->email)->whereKeyNot($m->id)->latest()->limit(5)->get(),
        ]);
    }

    public function reply(Request $request, EmailSender $sender, int $message): RedirectResponse
    {
        $m = ContactSubmission::findOrFail($message);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $result = $sender->send(OutgoingEmail::fromText($m->email, 'Re: your message to Growvia', $data['body'], ['toName' => $m->name, 'category' => 'contact_reply']));
        if (! $result->ok()) {
            return back()->withInput()->with('error', 'Not sent: '.($result->error ?? 'no email provider is available.'));
        }
        $m->forceFill(['status' => 'replied', 'reply' => $data['body'], 'replied_at' => now(), 'replied_by' => $request->user()->id, 'read_at' => $m->read_at ?? now()])->save();
        AuditLog::record('admin.contact_replied', null, ['message' => $m->id, 'by' => $request->user()->email]);

        return redirect()->route('admin.contact.show', $m->id)->with('status', 'Reply sent to '.$m->email.'.');
    }

    public function status(Request $request, int $message): RedirectResponse
    {
        $m = ContactSubmission::findOrFail($message);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ContactSubmission::STATUSES))]]);
        $m->forceFill(['status' => $data['status'], 'read_at' => $data['status'] === 'new' ? null : ($m->read_at ?? now())])->save();

        return back()->with('status', 'Marked as '.strtolower(ContactSubmission::STATUSES[$data['status']]).'.');
    }
}
