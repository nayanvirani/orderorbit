<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Coming soon" gate for the public website. Only visitors holding the preview
 * cookie (set by entering the owner password) see the site. The embedded app,
 * webhooks, storefront assets and legal pages are never gated.
 */
class SitePreviewGate
{
    public const COOKIE = 'oo_site_preview';

    /** Shopify requires these to be publicly reachable for the app listing. */
    private const ALWAYS_PUBLIC = ['site.privacy', 'site.terms', 'site.dpa'];

    public static function token(): ?string
    {
        $password = (string) config('site.preview_password');

        return $password === '' ? null : hash_hmac('sha256', 'site-preview', $password.config('app.key'));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = self::token();
        if ($token === null || in_array($request->route()?->getName(), self::ALWAYS_PUBLIC, true)
            || hash_equals($token, (string) $request->cookie(self::COOKIE))) {
            return $next($request);
        }

        return response()->view('site.coming-soon', ['failed' => false], 200)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }
}
