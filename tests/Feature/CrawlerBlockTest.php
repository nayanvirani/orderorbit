<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockCrawlers;
use Tests\TestCase;

class CrawlerBlockTest extends TestCase
{
    public function test_ai_seo_and_scraping_bots_are_refused_but_search_engines_and_shoppers_are_not(): void
    {
        foreach (['Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
            'Mozilla/5.0 (compatible; PerplexityBot/1.0)', 'CCBot/2.0 (https://commoncrawl.org/faq/)', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
            'Mozilla/5.0 (compatible; SemrushBot/7~bl)', 'python-requests/2.31.0', 'curl/8.4.0', 'Scrapy/2.11'] as $agent) {
            $this->assertTrue(BlockCrawlers::blocked($agent), $agent);
        }
        foreach (['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)', 'facebookexternalhit/1.1', 'Slackbot-LinkExpanding 1.0', 'WhatsApp/2.23',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36'] as $agent) {
            $this->assertFalse(BlockCrawlers::blocked($agent), $agent);
        }
    }

    public function test_the_website_refuses_bots_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; GPTBot/1.2)'])->assertForbidden();
        $this->get('/pricing', ['User-Agent' => 'Mozilla/5.0 (compatible; AhrefsBot/7.0)'])->assertForbidden();
        $this->get('/up', ['User-Agent' => 'curl/8.4.0'])->assertOk();
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertOk()->assertHeader('X-Robots-Tag');
    }
}
