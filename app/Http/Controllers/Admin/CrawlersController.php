<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Crawlers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Crawlers & SEO: which bots may crawl the public website (search engines, AI, SEO, scraping
 * tools, link previews), extra user agents, hidden paths and how strictly it's enforced.
 */
class CrawlersController extends Controller
{
    public function show(): View
    {
        return view('admin.crawlers', [
            'groups' => Crawlers::GROUPS,
            'settings' => Crawlers::settings(),
            'robots' => Crawlers::robotsTxt(route('site.sitemap')),
            'tag' => Crawlers::robotsTag(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'blocked' => ['array'], 'blocked.*' => ['string', 'max:80'],
            'custom_blocked' => ['nullable', 'string', 'max:5000'], 'custom_allowed' => ['nullable', 'string', 'max:5000'], 'paths' => ['nullable', 'string', 'max:5000'],
        ]);
        Crawlers::save($request->only(['blocked', 'custom_blocked', 'custom_allowed', 'paths', 'enforce', 'noai']));
        AuditLog::record('admin.crawlers_saved', null, ['by' => $request->user()->email, 'search_open' => Crawlers::searchOpen()]);

        return back()->with('status', 'Crawler settings saved. robots.txt, page tags and blocking are updated.');
    }

    public function reset(Request $request): RedirectResponse
    {
        Crawlers::reset();
        AuditLog::record('admin.crawlers_reset', null, ['by' => $request->user()->email]);

        return back()->with('status', 'Crawler settings reset to the defaults.');
    }
}
