<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps AI crawlers, AI agents, SEO and scraping bots off the public website (/robots.txt asks
 * the same; this enforces it for bots that ignore it), and search engines too while search
 * indexing is off (config site.search_engines). Link previews (Slack, WhatsApp, social networks)
 * still get the pages. The app, webhooks and storefront files are not affected.
 */
class BlockCrawlers
{
    /** Lower-case parts of the user agents that are refused. */
    public const BLOCKED = [
        // AI crawlers, training bots and agents
        'gptbot', 'chatgpt-user', 'oai-searchbot', 'operator', 'claudebot', 'claude-user', 'claude-searchbot', 'claude-web',
        'anthropic-ai', 'google-extended', 'googleother', 'google-cloudvertexbot', 'applebot-extended', 'meta-externalagent',
        'meta-externalfetcher', 'facebookbot', 'perplexitybot', 'perplexity-user', 'amazonbot', 'ccbot', 'bytespider',
        'cohere-ai', 'cohere-training-data-crawler', 'diffbot', 'duckassistbot', 'youbot', 'mistralai-user', 'ai2bot',
        'petalbot', 'timpibot', 'omgili', 'imagesiftbot', 'img2dataset', 'kangaroo bot', 'pangubot', 'sidetrade',
        'velenpublicwebcrawler', 'webzio-extended', 'iaskspider', 'isscyberriskcrawler', 'firecrawl', 'brightbot',
        // SEO, marketing and data-mining crawlers
        'ahrefsbot', 'ahrefssiteaudit', 'semrushbot', 'siteauditbot', 'mj12bot', 'dotbot', 'rogerbot', 'blexbot',
        'serpstatbot', 'dataforseobot', 'barkrowler', 'seokicks', 'seznambot', 'megaindex', 'linkpadbot', 'zoominfobot',
        'screaming frog', 'sitebulb', 'netcraftsurveyagent', 'censysinspect', 'expanseinc', 'internet-measurement',
        'magpie-crawler', 'mauibot', 'awario', 'buck/', 'grapeshot', 'proximic', 'turnitinbot', 'archive.org_bot', 'ia_archiver',
        // Scraping tools and libraries
        'scrapy', 'python-requests', 'python-urllib', 'aiohttp', 'httpx', 'go-http-client', 'libwww-perl', 'wget', 'curl/',
        'okhttp', 'java/', 'apache-httpclient', 'node-fetch', 'axios/', 'undici', 'headlesschrome', 'phantomjs', 'puppeteer',
        'playwright', 'selenium', 'httrack', 'nutch', 'heritrix',
    ];

    /** Search engine crawlers, refused while search indexing is off (config site.search_engines). */
    public const SEARCH_ENGINES = [
        // Crawler names only: shoppers' browsers (Yandex Browser, Cốc Cốc, Naver and Ecosia apps) must not match.
        'googlebot', 'google-inspectiontool', 'adsbot-google', 'mediapartners-google', 'storebot-google', 'bingbot', 'bingpreview',
        'msnbot', 'adidxbot', 'duckduckbot', 'yahoo! slurp', 'yandexbot', 'yandeximages', 'yandex.com/bots', 'baiduspider',
        'sogou web spider', 'exabot', 'qwantify', 'qwantbot', 'mojeek', 'applebot', 'yeti/', 'coccocbot', 'daumoa', 'seznambot',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $agent = strtolower((string) $request->userAgent());

        // Local and test runs drive the site with headless browsers and HTTP clients.
        if (! app()->environment('local', 'testing') && $agent !== '' && $this->blocked($agent)) {
            return response("Automated access to this website isn't allowed.\n", 403, ['Content-Type' => 'text/plain; charset=UTF-8', 'X-Robots-Tag' => 'noindex, noai, noimageai']);
        }

        $response = $next($request);
        // Added to any robots header already set (e.g. "noindex, nofollow" while the site is coming soon).
        $current = (string) $response->headers->get('X-Robots-Tag', '');
        $tags = config('site.search_engines') ? 'noai, noimageai' : 'noindex, nofollow, noai, noimageai';
        $response->headers->set('X-Robots-Tag', implode(', ', array_unique(array_filter(array_map('trim', explode(',', $current.','.$tags))))));

        return $response;
    }

    public static function blocked(string $agent): bool
    {
        $agent = strtolower($agent);
        foreach (config('site.search_engines') ? self::BLOCKED : array_merge(self::BLOCKED, self::SEARCH_ENGINES) as $part) {
            if (str_contains($agent, $part)) {
                return true;
            }
        }

        return false;
    }
}
