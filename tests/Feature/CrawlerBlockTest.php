<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Crawlers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Crawlers & SEO (Internal Admin): which bots may crawl the public website, enforced by
 * /robots.txt, the pages' robots tags and BlockCrawlers.
 */
class CrawlerBlockTest extends TestCase
{
    use RefreshDatabase;

    private const AI = 'Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)';

    private const SEO = 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)';

    private const GOOGLE = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    private const BING = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Ava', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => 'super_admin']);
    }

    /** Saves the admin form (as in tests, so the CSRF check is skipped), then serves like production. */
    private function save(User $admin, string $url, array $form = []): void
    {
        $this->app['env'] = 'testing';
        $this->actingAs($admin)->post($url, $form)->assertRedirect();
        $this->app['env'] = 'production';
    }

    /** Admin form input: every bot of the given groups blocked. */
    private function blocking(array $groups, array $extra = []): array
    {
        $bots = collect($groups)->flatMap(fn ($g) => array_keys(Crawlers::GROUPS[$g]['bots']))->all();

        return $extra + ['blocked' => $bots, 'paths' => "/app\n/admin", 'enforce' => '1', 'noai' => '1'];
    }

    public function test_by_default_every_crawler_is_kept_out_but_people_and_link_previews_are_not(): void
    {
        foreach ([self::AI, self::SEO, self::GOOGLE, self::BING, 'CCBot/2.0', 'python-requests/2.31.0', 'curl/8.4.0'] as $agent) {
            $this->assertTrue(Crawlers::blocks($agent), $agent);
        }
        foreach ([self::BROWSER, 'facebookexternalhit/1.1', 'Slackbot-LinkExpanding 1.0', 'WhatsApp/2.23',
            'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 YaBrowser/24.10 Safari/537.36',
            'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36 NAVER(inapp; search; 2000; 12.6.4)'] as $agent) {
            $this->assertFalse(Crawlers::blocks($agent), $agent);
        }
        $this->assertFalse(Crawlers::searchOpen());
        $this->assertSame('noindex, nofollow, noai, noimageai', Crawlers::robotsTag());
    }

    public function test_the_website_refuses_blocked_bots_and_tags_its_pages(): void
    {
        $this->app['env'] = 'production';

        $this->get('/', ['User-Agent' => self::AI])->assertForbidden();
        $this->get('/pricing', ['User-Agent' => self::GOOGLE])->assertForbidden();
        $this->get('/up', ['User-Agent' => 'curl/8.4.0'])->assertOk();
        $this->get('/', ['User-Agent' => self::BROWSER])->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noai, noimageai')
            ->assertSee('content="noindex, nofollow, noai, noimageai"', false);

        $robots = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();
        $this->assertStringContainsString("User-agent: GPTBot\n", $robots);
        $this->assertStringContainsString("User-agent: facebookexternalhit\n", $robots);
        $this->assertStringContainsString("User-agent: *\nDisallow: /\n", $robots);
        $this->assertStringNotContainsString('Sitemap:', $robots);
    }

    public function test_the_admin_opens_the_site_to_search_engines_bot_by_bot(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/crawlers')->assertOk()->assertSee('Crawlers &amp; SEO', false)->assertSee('Googlebot')->assertSee('Hidden from search engines');

        // Allow every search engine except Bing; keep AI, SEO and scraping tools blocked.
        $form = $this->blocking(['ai', 'seo', 'scrapers']);
        $form['blocked'][] = 'Bingbot';
        $this->save($admin, '/admin/crawlers', $form);

        $this->assertTrue(Crawlers::searchOpen());
        $this->get('/', ['User-Agent' => self::GOOGLE])->assertOk()->assertSee('content="index, follow, noai, noimageai"', false);
        $this->get('/', ['User-Agent' => self::BING])->assertForbidden();
        $this->get('/', ['User-Agent' => self::AI])->assertForbidden();

        $robots = $this->get('/robots.txt')->getContent();
        $this->assertStringContainsString("User-agent: Bingbot\n", $robots);
        $this->assertStringNotContainsString("User-agent: Googlebot\n", $robots);
        $this->assertStringContainsString("User-agent: *\nDisallow: /app\nDisallow: /admin\n", $robots);
        $this->assertStringContainsString('Sitemap: ', $robots);
    }

    public function test_extra_rules_enforcement_and_noai_can_be_turned_off(): void
    {
        $admin = $this->admin();

        $this->save($admin, '/admin/crawlers', $this->blocking(['search', 'ai'], ['custom_blocked' => "EvilScraper\n", 'custom_allowed' => "UptimeRobot\n"]));
        $this->get('/', ['User-Agent' => 'EvilScraper/1.0'])->assertForbidden();
        $this->get('/', ['User-Agent' => 'Mozilla/5.0+(compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)'])->assertOk();
        $this->get('/', ['User-Agent' => 'curl/8.4.0'])->assertOk(); // scraping tools allowed here
        $this->assertStringContainsString("User-agent: EvilScraper\n", $this->get('/robots.txt')->getContent());

        // robots.txt and tags only: nothing refused on the server, and no noai tag.
        $this->save($admin, '/admin/crawlers', $this->blocking(['ai'], ['enforce' => '0', 'noai' => '0']));
        $this->get('/', ['User-Agent' => self::AI])->assertOk()->assertHeader('X-Robots-Tag', 'index, follow'); // search engines allowed in this step

        $this->save($admin, '/admin/crawlers/reset');
        $this->assertSame(Crawlers::defaults(), Crawlers::settings());
    }
}
