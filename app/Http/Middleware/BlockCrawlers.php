<?php

namespace App\Http\Middleware;

use App\Support\Crawlers;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses the bots blocked in the Internal Admin (Crawlers & SEO) on the public website, for bots
 * that ignore /robots.txt, and tags every page with the robots rules (index / noindex, noai).
 * The app, webhooks and storefront files are not affected.
 */
class BlockCrawlers
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = Crawlers::settings();

        // Local and test runs drive the site with headless browsers and HTTP clients.
        if ($settings['enforce'] && ! app()->environment('local', 'testing') && Crawlers::blocks((string) $request->userAgent(), $settings)) {
            return response("Automated access to this website isn't allowed.\n", 403, ['Content-Type' => 'text/plain; charset=UTF-8', 'X-Robots-Tag' => 'noindex, nofollow, noai, noimageai']);
        }

        $response = $next($request);
        // Added to any robots header already set (e.g. "noindex, nofollow" while the site is coming soon).
        $tags = explode(',', $response->headers->get('X-Robots-Tag', '').','.Crawlers::robotsTag());
        $response->headers->set('X-Robots-Tag', implode(', ', array_unique(array_filter(array_map('trim', $tags)))));

        return $response;
    }
}
