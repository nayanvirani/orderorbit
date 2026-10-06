<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Content;
use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Website content (super admin): every line of the public website is editable, lists can be
 * added to, reordered and trimmed, and a reset brings back the built-in text.
 */
class WebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super_admin'): User
    {
        return User::where('email', $role.'@orderorbit.space')->first() ?? User::forceCreate(['name' => 'Ava', 'email' => $role.'@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => $role]);
    }

    private function save(string $key, array $data, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin())->post('/admin/content/'.$key, ['data' => json_encode($data)]);
    }

    public function test_every_content_page_and_item_opens_in_the_editor(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/content')->assertOk()->assertSee('Website content')->assertSee('Feature pages')->assertSee('Guides');
        foreach (SiteContent::catalogue() as $items) {
            foreach (array_keys($items) as $key) {
                $this->actingAs($admin)->get('/admin/content/'.$key)->assertOk();
            }
        }
        $this->actingAs($admin)->get('/admin/content/page.nope')->assertNotFound();
    }

    public function test_a_page_edit_reaches_the_website_and_reset_brings_the_built_in_text_back(): void
    {
        $home = SiteContent::page('home');
        $home['hero']['title'] = 'Grow every order, *with one app.*';
        $home['hero']['checks'] = ['Billed through Shopify'];                          // removed two
        $home['faq']['faqs'] = array_reverse($home['faq']['faqs']);                      // reordered
        $home['faq']['faqs'][] = ['A brand-new question?', 'Added from the **admin**.']; // added
        $home['footer_unknown'] = 'dropped';                                              // not part of the page
        $this->save('page.home', $home)->assertSessionHas('status', 'Home saved. It\'s live on the website.');

        $this->get('/')->assertOk()
            ->assertSee('Grow every order, <span class="hl">with one app.</span>', false)
            ->assertSee('A brand-new question?')
            ->assertSee('Added from the <strong>admin</strong>.', false)
            ->assertDontSee('Free plan, no card');
        $this->assertArrayNotHasKey('footer_unknown', SiteContent::page('home'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.website_content_saved']);

        $this->actingAs($this->admin('operations'))->post('/admin/content/page.home/reset')->assertRedirect();
        $this->get('/')->assertSee('Sell more to every shopper,')->assertSee('Free plan, no card')->assertDontSee('A brand-new question?');
    }

    public function test_feature_guide_and_list_edits_reach_every_page_that_uses_them(): void
    {
        $bundles = SiteContent::get('feature.bundles');
        $bundles['name'] = 'Smart Bundles';
        $bundles['h1'] = 'Bundles, *rebuilt.*';
        $this->save('feature.bundles', $bundles);
        $this->get('/features/bundles')->assertSee('Bundles, <span class="hl">rebuilt.</span>', false);
        $this->get('/features')->assertSee('Smart Bundles');
        $this->get('/')->assertSee('Smart Bundles'); // the menu and the feature cards
        $this->assertSame('bundle', Content::features()['bundles']['icon'], 'Fields the editor hides keep their value.');

        $guide = SiteContent::get('guide.ab-testing');
        $guide['sections']['overview'][0] = 'Testing, explained';
        $guide['sections']['overview'][1][] = ['note', 'A note added in the admin.'];
        $this->save('guide.ab-testing', $guide);
        $this->get('/docs/ab-testing')->assertOk()->assertSee('Testing, explained')->assertSee('A note added in the admin.');

        $help = SiteContent::get('list.help');
        $help[0]['articles'][] = ['Can I edit this help article?', 'Yes, in Website content.'];
        $this->save('list.help', $help);
        $this->get('/help')->assertSee('Can I edit this help article?');

        $blog = SiteContent::get('list.blog');
        $blog[0]['badge'] = '';
        $blog[0]['title'] = 'A published post';
        $this->save('list.blog', $blog);
        $this->get('/blog')->assertSee('A published post');
    }

    public function test_copy_is_escaped_and_unsafe_links_are_dropped(): void
    {
        $about = SiteContent::page('about');
        $about['paragraphs'] = ['<script>alert(1)</script> see [this](javascript:alert(1)) and [that](/contact)'];
        $this->save('page.about', $about);

        $this->get('/about')->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('<a href="#">this</a>', false)
            ->assertSee('<a href="/contact">that</a>', false);
    }

    public function test_placeholders_fill_in_from_plans_and_the_date(): void
    {
        $this->assertSame('© '.date('Y').' OrderOrbit Space. Built for Shopify.', SiteContent::plain('© {year} OrderOrbit Space. Built for Shopify.'));
        $cheapest = number_format(min(array_filter(array_column(\App\Support\Plans::public(), 'price'))), 2);
        $this->assertSame('From $'.$cheapest, SiteContent::plain('From ${from_price}'));
        $this->assertSame('Try Bundles on your store', SiteContent::plain('Try {name} on your store', ['name' => 'Bundles']));
    }

    public function test_support_admins_cannot_edit_content(): void
    {
        $support = $this->admin('support');
        $this->actingAs($support)->get('/admin/content')->assertForbidden();
        $this->save('page.home', ['hero' => ['title' => 'Hacked']], $support)->assertForbidden();
        $this->assertSame('Sell more to every shopper, *without fighting your theme.*', SiteContent::page('home')['hero']['title']);
    }

    public function test_error_pages_use_the_edited_text(): void
    {
        $errors = SiteContent::page('errors');
        $errors['404']['heading'] = 'Lost in *space.*';
        $this->save('page.errors', $errors);
        $this->get('/no-such-page')->assertNotFound()->assertSee('Lost in <span class="hl">space.</span>', false);
    }
}
