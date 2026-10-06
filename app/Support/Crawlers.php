<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Who may crawl the public website, set in the Internal Admin (Crawlers & SEO): each known bot
 * can be blocked or allowed, plus extra user agents, hidden paths, server enforcement and the
 * noai tags. /robots.txt, the pages' robots tags and BlockCrawlers all follow these settings.
 */
class Crawlers
{
    private const KEY = 'crawlers';

    private const CACHE = 'crawler-settings:v1';

    /**
     * Known bots by group. Each bot: robots.txt name => lower-case parts of its user agent.
     * "blocked" is the group's default; groups without "robots" don't read robots.txt.
     */
    public const GROUPS = [
        'search' => [
            'label' => 'Search engines',
            'help' => 'Google, Bing and other search engines. Blocked: the website stays out of search results (no SEO).',
            'blocked' => true,
            'bots' => [
                'Googlebot' => ['googlebot', 'google-inspectiontool', 'adsbot-google', 'mediapartners-google', 'storebot-google'],
                'Bingbot' => ['bingbot', 'bingpreview', 'msnbot', 'adidxbot'],
                'DuckDuckBot' => ['duckduckbot'],
                'Slurp' => ['yahoo! slurp'],
                'YandexBot' => ['yandexbot', 'yandeximages', 'yandex.com/bots'],
                'Baiduspider' => ['baiduspider'],
                'Applebot' => ['applebot/'],
                'SeznamBot' => ['seznambot'],
                'Sogou web spider' => ['sogou web spider'],
                'Exabot' => ['exabot'],
                'Qwantbot' => ['qwantify', 'qwantbot'],
                'MojeekBot' => ['mojeek'],
                'Yeti' => ['yeti/'],
                'coccocbot' => ['coccocbot'],
                'Daumoa' => ['daumoa'],
            ],
        ],
        'ai' => [
            'label' => 'AI crawlers and agents',
            'help' => 'AI training bots, AI search and AI assistants that browse for users.',
            'blocked' => true,
            'bots' => [
                'GPTBot' => ['gptbot'], 'ChatGPT-User' => ['chatgpt-user'], 'OAI-SearchBot' => ['oai-searchbot'], 'Operator' => ['openai operator', 'operator/'],
                'ClaudeBot' => ['claudebot'], 'Claude-User' => ['claude-user'], 'Claude-SearchBot' => ['claude-searchbot'], 'Claude-Web' => ['claude-web'], 'anthropic-ai' => ['anthropic-ai'],
                'Google-Extended' => ['google-extended'], 'GoogleOther' => ['googleother'], 'Google-CloudVertexBot' => ['google-cloudvertexbot'],
                'Applebot-Extended' => ['applebot-extended'],
                'meta-externalagent' => ['meta-externalagent'], 'meta-externalfetcher' => ['meta-externalfetcher'], 'FacebookBot' => ['facebookbot'],
                'PerplexityBot' => ['perplexitybot'], 'Perplexity-User' => ['perplexity-user'],
                'Amazonbot' => ['amazonbot'], 'CCBot' => ['ccbot'], 'Bytespider' => ['bytespider'],
                'cohere-ai' => ['cohere-ai'], 'cohere-training-data-crawler' => ['cohere-training-data-crawler'], 'Diffbot' => ['diffbot'],
                'DuckAssistBot' => ['duckassistbot'], 'YouBot' => ['youbot'], 'MistralAI-User' => ['mistralai-user'], 'AI2Bot' => ['ai2bot'],
                'PetalBot' => ['petalbot'], 'Timpibot' => ['timpibot'], 'omgilibot' => ['omgili'], 'ImagesiftBot' => ['imagesiftbot'],
                'img2dataset' => ['img2dataset'], 'Kangaroo Bot' => ['kangaroo bot'], 'PanguBot' => ['pangubot'], 'Sidetrade indexer bot' => ['sidetrade'],
                'VelenPublicWebCrawler' => ['velenpublicwebcrawler'], 'Webzio-Extended' => ['webzio-extended'], 'iaskspider' => ['iaskspider'],
                'FirecrawlAgent' => ['firecrawl'], 'Brightbot' => ['brightbot'],
            ],
        ],
        'seo' => [
            'label' => 'SEO and data-mining crawlers',
            'help' => 'Backlink, keyword and marketing tools (Ahrefs, Semrush…), archives and site auditors.',
            'blocked' => true,
            'bots' => [
                'AhrefsBot' => ['ahrefsbot', 'ahrefssiteaudit'], 'SemrushBot' => ['semrushbot', 'siteauditbot'], 'MJ12bot' => ['mj12bot'], 'DotBot' => ['dotbot'],
                'rogerbot' => ['rogerbot'], 'BLEXBot' => ['blexbot'], 'serpstatbot' => ['serpstatbot'], 'DataForSeoBot' => ['dataforseobot'],
                'Barkrowler' => ['barkrowler'], 'SEOkicks' => ['seokicks'], 'MegaIndex' => ['megaindex'], 'ZoominfoBot' => ['zoominfobot'],
                'Screaming Frog SEO Spider' => ['screaming frog'], 'Sitebulb' => ['sitebulb'], 'magpie-crawler' => ['magpie-crawler'], 'MauiBot' => ['mauibot'],
                'Awario' => ['awario'], 'BUbiNG' => ['bubing'], 'archive.org_bot' => ['archive.org_bot'], 'ia_archiver' => ['ia_archiver'],
                'NetcraftSurveyAgent' => ['netcraftsurveyagent'], 'CensysInspect' => ['censysinspect'], 'Expanse' => ['expanseinc'],
            ],
        ],
        'scrapers' => [
            'label' => 'Scraping tools',
            'help' => 'Scripts, command-line tools and headless browsers used to copy websites. Most ignore robots.txt, so the server refuses them.',
            'blocked' => true,
            'robots' => false,
            'bots' => [
                'Scrapy' => ['scrapy'], 'HTTrack' => ['httrack'], 'Nutch' => ['nutch'], 'Heritrix' => ['heritrix'],
                'curl' => ['curl/'], 'Wget' => ['wget'], 'Python' => ['python-requests', 'python-urllib', 'aiohttp', 'httpx'],
                'Go' => ['go-http-client'], 'Java' => ['java/', 'apache-httpclient', 'okhttp'], 'Node.js' => ['node-fetch', 'axios/', 'undici'],
                'Perl' => ['libwww-perl'], 'Headless browsers' => ['headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium'],
            ],
        ],
        'previews' => [
            'label' => 'Link previews',
            'help' => 'The cards shown when someone shares a link to the website in chats and social networks.',
            'blocked' => false,
            'bots' => [
                'facebookexternalhit' => ['facebookexternalhit'], 'Twitterbot' => ['twitterbot'], 'LinkedInBot' => ['linkedinbot'],
                'Slackbot' => ['slackbot'], 'WhatsApp' => ['whatsapp'], 'TelegramBot' => ['telegrambot'], 'Discordbot' => ['discordbot'],
                'Pinterestbot' => ['pinterestbot'],
            ],
        ],
    ];

    public const DEFAULT_PATHS = ['/app', '/admin', '/webhooks', '/templates/previews.json'];

    /** @return array{blocked: array<string, bool>, custom_blocked: list<string>, custom_allowed: list<string>, paths: list<string>, enforce: bool, noai: bool} */
    public static function settings(): array
    {
        $saved = [];
        try {
            $saved = Cache::rememberForever(self::CACHE, function () {
                $row = Schema::hasTable('platform_settings') ? DB::table('platform_settings')->where('key', self::KEY)->value('value') : null;

                return $row ? (array) json_decode($row, true) : [];
            });
        } catch (Throwable) {
            // No database yet: defaults.
        }

        return self::merge($saved);
    }

    /** Defaults, with search engines open only when SITE_SEARCH_ENGINES is set. */
    public static function defaults(): array
    {
        $blocked = [];
        foreach (self::GROUPS as $key => $group) {
            $default = $key === 'search' ? ! config('site.search_engines') : $group['blocked'];
            foreach (array_keys($group['bots']) as $bot) {
                $blocked[$bot] = $default;
            }
        }

        return ['blocked' => $blocked, 'custom_blocked' => [], 'custom_allowed' => [], 'paths' => self::DEFAULT_PATHS, 'enforce' => true, 'noai' => true];
    }

    private static function merge(array $saved): array
    {
        $d = self::defaults();
        foreach ($d['blocked'] as $bot => $default) {
            if (isset($saved['blocked'][$bot])) {
                $d['blocked'][$bot] = (bool) $saved['blocked'][$bot];
            }
        }
        foreach (['custom_blocked', 'custom_allowed', 'paths'] as $list) {
            if (isset($saved[$list]) && is_array($saved[$list])) {
                $d[$list] = array_values($saved[$list]);
            }
        }
        foreach (['enforce', 'noai'] as $flag) {
            if (isset($saved[$flag])) {
                $d[$flag] = (bool) $saved[$flag];
            }
        }

        return $d;
    }

    /** Saves the admin form: blocked bots (names), extra user agents, paths and flags. */
    public static function save(array $input): void
    {
        $lines = fn ($text, $max) => array_slice(array_values(array_unique(array_filter(array_map(
            fn ($l) => mb_substr(trim(preg_replace('/[\x00-\x1f]/', '', $l)), 0, $max), preg_split('/\R/', (string) $text)
        )))), 0, 100);
        $blocked = [];
        $chosen = array_flip((array) ($input['blocked'] ?? []));
        foreach (self::GROUPS as $group) {
            foreach (array_keys($group['bots']) as $bot) {
                $blocked[$bot] = isset($chosen[$bot]);
            }
        }
        $paths = array_values(array_filter($lines($input['paths'] ?? '', 200), fn ($p) => str_starts_with($p, '/')));
        $value = [
            'blocked' => $blocked,
            'custom_blocked' => $lines($input['custom_blocked'] ?? '', 120),
            'custom_allowed' => $lines($input['custom_allowed'] ?? '', 120),
            'paths' => $paths,
            'enforce' => filter_var($input['enforce'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'noai' => filter_var($input['noai'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
        DB::table('platform_settings')->updateOrInsert(['key' => self::KEY], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(self::CACHE);
    }

    public static function reset(): void
    {
        DB::table('platform_settings')->where('key', self::KEY)->delete();
        Cache::forget(self::CACHE);
    }

    /** Whether a request with this user agent is refused (always-allowed user agents win). */
    public static function blocks(string $agent, ?array $settings = null): bool
    {
        $s = $settings ?? self::settings();
        $agent = strtolower($agent);
        if ($agent === '') {
            return false;
        }
        foreach ($s['custom_allowed'] as $part) {
            if (str_contains($agent, strtolower($part))) {
                return false;
            }
        }
        foreach ($s['custom_blocked'] as $part) {
            if (str_contains($agent, strtolower($part))) {
                return true;
            }
        }
        foreach (self::GROUPS as $group) {
            foreach ($group['bots'] as $bot => $parts) {
                if (! empty($s['blocked'][$bot])) {
                    foreach ($parts as $part) {
                        if (str_contains($agent, $part)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /** Pages may be indexed when at least one search engine is allowed. */
    public static function searchOpen(?array $settings = null): bool
    {
        $s = $settings ?? self::settings();

        return collect(array_keys(self::GROUPS['search']['bots']))->contains(fn ($bot) => empty($s['blocked'][$bot]));
    }

    /** The robots meta tag and X-Robots-Tag value for public pages. */
    public static function robotsTag(): string
    {
        $s = self::settings();

        return implode(', ', array_filter([self::searchOpen($s) ? 'index, follow' : 'noindex, nofollow', $s['noai'] ? 'noai, noimageai' : null]));
    }

    /** The /robots.txt text. */
    public static function robotsTxt(string $sitemap): string
    {
        $s = self::settings();
        $out = ["# OrderOrbit Space - crawler rules (set in the Internal Admin: Crawlers & SEO)", ''];
        $denied = [];
        $allowed = [];
        foreach (self::GROUPS as $key => $group) {
            if (($group['robots'] ?? true) === false) {
                continue;
            }
            foreach (array_keys($group['bots']) as $bot) {
                empty($s['blocked'][$bot]) ? $allowed[$key][] = $bot : $denied[$group['label']][] = $bot;
            }
        }
        foreach ($s['custom_blocked'] as $agent) {
            if (preg_match('/^[A-Za-z0-9._\- ]+$/', $agent)) {
                $denied['Extra blocked user agents'][] = $agent;
            }
        }
        if ($denied) {
            $out[] = '# Blocked';
            foreach ($denied as $label => $bots) {
                $out[] = '# '.$label;
                foreach ($bots as $bot) {
                    $out[] = 'User-agent: '.$bot;
                }
            }
            $out[] = 'Disallow: /';
            $out[] = '';
        }
        $paths = fn () => array_map(fn ($p) => 'Disallow: '.$p, $s['paths']);
        if (self::searchOpen($s)) {
            $out[] = '# Everyone else';
            $out[] = 'User-agent: *';
            array_push($out, ...($s['paths'] ? $paths() : ['Disallow:']));
            $out[] = '';
            $out[] = 'Sitemap: '.$sitemap;
        } else {
            // Closed to crawlers: name the allowed ones (link previews, any allowed bot) first.
            $named = array_merge(...array_values($allowed ?: [[]]));
            if ($named) {
                $out[] = '# Allowed';
                foreach ($named as $bot) {
                    $out[] = 'User-agent: '.$bot;
                }
                $out[] = 'Allow: /';
                array_push($out, ...$paths());
                $out[] = '';
            }
            $out[] = '# Everyone else, search engines included';
            $out[] = 'User-agent: *';
            $out[] = 'Disallow: /';
        }

        return implode("\n", $out)."\n";
    }
}
