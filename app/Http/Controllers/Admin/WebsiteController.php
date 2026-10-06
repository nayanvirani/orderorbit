<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\SiteTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Website design: the public website's colours, fonts and corner radii (App\Support\SiteTheme),
 * with a live preview of the real pages.
 */
class WebsiteController extends Controller
{
    public function show(): View
    {
        return view('admin.website', [
            'groups' => SiteTheme::FIELDS,
            'values' => SiteTheme::get(),
            'defaults' => SiteTheme::defaults(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $before = SiteTheme::get();
        $rejected = SiteTheme::save((array) $request->input('theme', []));
        $after = SiteTheme::get();
        $changed = array_keys(array_diff_assoc($after, $before));
        AuditLog::record('admin.website_design_saved', null, ['changed' => $changed, 'by' => $request->user()->email]);

        return back()->with('status', $rejected
            ? 'Saved, except: '.implode(', ', $rejected).'. Colours need #rrggbb, fonts must exist on Google Fonts, and sizes must be within their limits.'
            : 'Website design saved. It\'s live on every public page.');
    }

    public function reset(Request $request): RedirectResponse
    {
        SiteTheme::reset();
        AuditLog::record('admin.website_design_reset', null, ['by' => $request->user()->email]);

        return back()->with('status', 'Website design reset to the built-in look.');
    }
}
