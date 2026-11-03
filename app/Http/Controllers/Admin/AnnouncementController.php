<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Announcements to merchants (G41), written and scheduled by the Growvia team. */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::withCount(['views', 'views as dismissed_count' => fn ($q) => $q->whereNotNull('dismissed_at')])->latest('starts_at')->get(),
        ]);
    }

    public function form(Request $request, ?int $announcement = null): View
    {
        $a = $announcement ? Announcement::findOrFail($announcement) : null;
        $template = $a ? null : (Announcement::TEMPLATES[$request->query('template', 'feature')] ?? Announcement::TEMPLATES['feature']);

        return view('admin.announcements.form', [
            'a' => $a, 'template' => $template, 'templateKey' => $a ? ($a->price_change_at ? 'price' : null) : $request->query('template', 'feature'),
            'plans' => \App\Models\Plan::orderBy('price')->pluck('name', 'key')->all(),
            'modules' => collect(\App\Support\Modules::all())->map(fn ($m) => $m[0])->all(),
            'stores' => Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->orderBy('shop_domain')->get(['id', 'shop_domain']),
        ]);
    }

    public function save(Request $request, ?int $announcement = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:2000'],
            'cta_text' => ['nullable', 'string', 'max:60', 'required_with:cta_url'], 'cta_url' => ['nullable', 'string', 'max:500', 'regex:#^(/app(/|$)|https://)#', 'required_with:cta_text'],
            'tone' => ['required', Rule::in(array_keys(Announcement::TONES))], 'placement' => ['required', Rule::in(array_keys(Announcement::PLACEMENTS))],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'plans' => ['array', 'required_if:audience,plans'], 'plans.*' => ['string'], 'module' => ['nullable', 'string', 'required_if:audience,module'],
            'store_ids' => ['array', 'required_if:audience,stores'], 'store_ids.*' => ['integer'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'price_change_at' => ['nullable', 'date'],
        ], [
            'cta_url.regex' => 'The button link must be a Growvia page (starting /app) or a full https:// address.',
            'ends_at.after' => 'The end must be after the start.',
        ]);
        $isPrice = $request->boolean('price_change');
        if ($isPrice) {
            // Shopify: tell merchants at least 30 days before a price change; it can't be closed and every owner is emailed.
            if (empty($data['price_change_at'])) {
                return back()->withInput()->withErrors(['price_change_at' => 'Enter the date the new price applies.']);
            }
            if (Carbon::parse($data['starts_at'])->gt(Carbon::parse($data['price_change_at'])->subDays(30))) {
                return back()->withInput()->withErrors(['starts_at' => 'A price change must be announced at least 30 days before the new price applies.']);
            }
        }
        $a = $announcement ? Announcement::findOrFail($announcement) : new Announcement(['created_by' => $request->user()->id]);
        $a->fill([
            'title' => $data['title'], 'body' => $data['body'], 'cta_text' => $data['cta_text'] ?? null, 'cta_url' => $data['cta_url'] ?? null,
            'tone' => $data['tone'], 'placement' => $isPrice ? 'every_page' : $data['placement'], 'audience' => $data['audience'],
            'plans' => $data['audience'] === 'plans' ? array_values($data['plans'] ?? []) : null,
            'module' => $data['audience'] === 'module' ? $data['module'] : null,
            'store_ids' => $data['audience'] === 'stores' ? array_map('intval', $data['store_ids'] ?? []) : null,
            'owners_only' => $request->boolean('owners_only'),
            'dismissible' => $isPrice ? false : $request->boolean('dismissible'),
            'email_owners' => $isPrice ? true : $request->boolean('email_owners'),
            'price_change_at' => $isPrice ? $data['price_change_at'] : null,
            'starts_at' => $data['starts_at'], 'ends_at' => $isPrice ? $data['price_change_at'] : ($data['ends_at'] ?? null),
        ])->save();
        AuditLog::record('admin.announcement_saved', null, ['announcement' => $a->id, 'title' => $a->title, 'by' => $request->user()->email]);

        return redirect()->route('admin.announcements')->with('status', '"'.$a->title.'" saved · '.strtolower($a->status()).'.');
    }

    public function show(int $announcement): View
    {
        $a = Announcement::findOrFail($announcement);

        return view('admin.announcements.show', ['a' => $a, 'views' => $a->views()->with(['store:id,shop_domain', 'user:id,first_name,last_name,email,role'])->latest('seen_at')->paginate(50)]);
    }

    public function delete(Request $request, int $announcement): RedirectResponse
    {
        $a = Announcement::findOrFail($announcement);
        abort_if($a->price_change_at !== null && $a->status() !== 'Scheduled', 422, 'A price change notice that has started is kept as the record of who saw it. End it instead.');
        $a->delete();
        AuditLog::record('admin.announcement_deleted', null, ['title' => $a->title, 'by' => $request->user()->email]);

        return redirect()->route('admin.announcements')->with('status', 'Announcement deleted.');
    }
}
