<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Website content: every line of the public website (App\Support\SiteContent), by page and
 * content item, with add/remove/reorder for lists and a reset to the built-in text.
 */
class ContentController extends Controller
{
    public function index(): View
    {
        return view('admin.content.index', [
            'catalogue' => SiteContent::catalogue(),
            'edited' => DB::table('site_contents')->pluck('updated_at', 'key')->all(),
        ]);
    }

    public function edit(string $key): View
    {
        $defaults = SiteContent::defaults($key);

        return view('admin.content.edit', [
            'key' => $key,
            'title' => SiteContent::title($key),
            'where' => collect(SiteContent::catalogue())->flatMap(fn ($items) => $items)->get($key)[1] ?? null,
            'values' => SiteContent::get($key),
            'defaults' => $defaults,
            'edited' => SiteContent::isEdited($key),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        SiteContent::defaults($key); // 404 for an unknown key
        $data = json_decode((string) $request->input('data'), true);
        if (! is_array($data)) {
            return back()->with('status', 'Nothing was saved: the form data was incomplete. Reload the page and try again.');
        }
        SiteContent::save($key, $data, $request->user()->email);
        AuditLog::record('admin.website_content_saved', null, ['key' => $key, 'by' => $request->user()->email]);

        return back()->with('status', SiteContent::title($key).' saved. It\'s live on the website.');
    }

    public function reset(Request $request, string $key): RedirectResponse
    {
        SiteContent::defaults($key);
        SiteContent::reset($key);
        AuditLog::record('admin.website_content_reset', null, ['key' => $key, 'by' => $request->user()->email]);

        return back()->with('status', SiteContent::title($key).' is back to the built-in text.');
    }
}
