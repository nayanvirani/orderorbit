<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Support\Content;
use App\Support\Features;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public website's search basics, and the Profit & store health pages that follow their flags. */
class WebsiteSeoTest extends TestCase
{
    use RefreshDatabase;

    private function release(array $flags): void
    {
        foreach ($flags as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
    }

    public function test_every_page_has_a_short_title_a_description_and_share_tags(): void
    {
        $this->release(['profit', 'protect', 'sales_insights', 'integrations', 'review_apps']);
        $paths = array_merge(['/', '/how-it-works', '/features', '/solutions', '/templates', '/pricing', '/resources', '/blog', '/help', '/contact', '/about', '/security', '/docs', '/terms', '/privacy', '/legal/billing'],
            array_map(fn ($s) => "/features/{$s}", array_keys(Content::features())),
            array_map(fn ($s) => "/solutions/{$s}", array_keys(Content::solutions())));
        foreach ($paths as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            preg_match('/<title>(.*?)<\/title>/s', $html, $t);
            preg_match('/<meta name="description" content="(.*?)"/', $html, $d);
            $title = html_entity_decode(trim($t[1] ?? ''), ENT_QUOTES);
            $description = html_entity_decode($d[1] ?? '', ENT_QUOTES);
            $this->assertNotSame('', $title, $path);
            $this->assertLessThanOrEqual(60, mb_strlen($title), "{$path}: {$title}");
            $this->assertGreaterThanOrEqual(50, mb_strlen($description), "{$path} needs a description");
            $this->assertLessThanOrEqual(160, mb_strlen($description), "{$path}: {$description}");
            $this->assertStringContainsString('og:image', $html, $path);
            $this->assertSame(1, substr_count($html, '<h1'), "{$path} has one main heading");
            if ($path !== '/') {
                $this->assertStringContainsString('"BreadcrumbList"', $html, $path);
            }
        }
        $this->assertStringContainsString('Paid plans from $', $this->get('/pricing')->getContent());
    }

    public function test_profit_and_store_health_pages_wait_for_their_flags(): void
    {
        $this->get('/features/profit')->assertNotFound();
        $this->get('/features')->assertOk()->assertDontSee('Profit and store health');
        $this->assertStringNotContainsString('/features/profit', $this->get('/sitemap.xml')->getContent());
        $this->assertContains('profit', Modules::coming());

        $this->release(['profit', 'protect']);
        $this->get('/features/profit')->assertOk()->assertSee('See what you really make on every order.')->assertSee('images/site/profit-dashboard.webp');
        $this->get('/features/protect')->assertOk()->assertSee('Catch money leaks before they cost you.');
        $this->get('/features/integrations')->assertNotFound(); // needs both integration flags
        $this->get('/features')->assertOk()->assertSee('Profit and store health')->assertSee('Profit Dashboard');
        $this->get('/')->assertOk()->assertSee('/features/profit', false);
        $this->assertStringContainsString('/features/profit', $this->get('/sitemap.xml')->getContent());
        $this->assertNotContains('profit', Modules::coming());
        $this->assertContains('klaviyo_ga4', Modules::coming());
    }

    public function test_long_blog_titles_drop_the_brand(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder'])->run();
        $post = \App\Models\BlogPost::live()->first();
        if (! $post) {
            $this->markTestSkipped('No blog posts are seeded in this environment.');
        }
        $post->forceFill(['seo_title' => 'Subscription Bundles on Shopify: Subscribe & Save Packs'])->save();
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('<title>Subscription Bundles on Shopify: Subscribe &amp; Save Packs</title>', false);
        $post->forceFill(['seo_title' => 'Short title'])->save();
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('<title>Short title | Growvia</title>', false);
    }
}
