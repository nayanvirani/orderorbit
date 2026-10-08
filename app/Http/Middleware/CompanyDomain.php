<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The main OrderOrbit domain (site.company_host) shows its own "coming soon" page. Growvia's
 * public website lives on APP_URL, so old links to its pages (features, pricing, legal, docs)
 * on the main domain are sent there. Only applies when the two hosts differ.
 */
class CompanyDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = strtolower((string) config('site.company_host'));
        $host = strtolower($request->getHost());
        $app = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($company === '' || $host === $app || ! in_array($host, [$company, 'www.'.$company], true)) {
            return $next($request);
        }
        if ($request->isMethod('GET') && $request->path() === '/') {
            return response()->view('site.company', ['growvia' => rtrim((string) config('app.url'), '/')])
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }
        $query = $request->getQueryString();

        return redirect()->away(rtrim((string) config('app.url'), '/').'/'.ltrim($request->path(), '/').($query ? '?'.$query : ''), $request->isMethod('GET') ? 301 : 307);
    }
}
