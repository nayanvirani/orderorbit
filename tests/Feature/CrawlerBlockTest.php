<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockCrawlers;
use Tests\TestCase;

class CrawlerBlockTest extends TestCase
{
    private const BOTS = ['Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
        'Mozilla/5.0 (compatible; PerplexityBot/1.0)', 'CCBot/2.0 (https://commoncrawl.org/faq/)', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
        'Mozilla/5.0 (compatible; SemrushBot/7~bl)', 'python-requests/2.31.0', 'curl/8.4.0', 'Scrapy/2.11'];

    private const SEARCH = ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        'DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)', 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)'];

    // Shoppers and link previews: never refused.
    private const PEOPLE = ['facebookexternalhit/1.1', 'Slackbot-LinkExpanding 1.0', 'WhatsApp/2.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 YaBrowser/24.10 Safari/537.36', 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36 NAVER(inapp; search; 2000; 12.6.4)'];

    public function test_with_search_off_every_crawler_is_refused_but_people_and_link_previews_are_not(): void
    {
        config(['site.search_engines' => false]);
        foreach ([...self::BOTS, ...self::SEARCH] as $agent) {
            $this->assertTrue(BlockCrawlers::blocked($agent), $agent);
        }
        foreach (self::PEOPLE as $agent) {
            $this->assertFalse(BlockCrawlers::blocked($agent), $agent);
        }
    }

    public function test_with_search_on_search_engines_may_crawl_but_ai_and_seo_bots_may_not(): void
    {
        config(['site.search_engines' => true]);
        foreach (self::BOTS as $agent) {
            $this->assertTrue(BlockCrawlers::blocked($agent), $agent);
        }
        foreach ([...self::SEARCH, ...self::PEOPLE] as $agent) {
            $this->assertFalse(BlockCrawlers::blocked($agent), $agent);
        }
    }

    public function test_the_website_refuses_crawlers_and_says_noindex_while_search_is_off(): void
    {
        $this->app['env'] = 'production';
        config(['site.search_engines' => false]);

        $this->get('/', ['User-Agent' => self::BOTS[0]])->assertForbidden();
        $this->get('/pricing', ['User-Agent' => self::SEARCH[0]])->assertForbidden();
        $this->get('/up', ['User-Agent' => 'curl/8.4.0'])->assertOk();
        $this->get('/', ['User-Agent' => self::PEOPLE[3]])->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noai, noimageai')
            ->assertSee('content="noindex, nofollow, noai, noimageai"', false);

        $robots = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();
        $this->assertStringContainsString("User-agent: GPTBot\n", $robots);
        $this->assertStringContainsString("User-agent: *\nDisallow: /\n", $robots);
        $this->assertStringNotContainsString('Sitemap:', $robots);
    }

    public function test_robots_txt_opens_the_site_to_search_engines_when_turned_on(): void
    {
        config(['site.search_engines' => true]);

        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString("User-agent: ClaudeBot\n", $robots);
        $this->assertStringContainsString("User-agent: *\nDisallow: /app\n", $robots);
        $this->assertStringContainsString('Sitemap: ', $robots);
        $this->get('/')->assertOk()->assertSee('content="index, follow, noai, noimageai"', false);
    }
}
