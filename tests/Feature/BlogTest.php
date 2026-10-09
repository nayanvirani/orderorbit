<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.preview_password' => null]);
    }

    private function admin(string $role = 'super_admin'): User
    {
        return User::forceCreate(['name' => 'Ava', 'email' => $role.'@growvia.test', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => $role]);
    }

    public function test_the_blog_starts_with_the_written_articles(): void
    {
        $this->assertSame(12, BlogPost::live()->count());
        $page = $this->get('/blog')->assertOk()->assertSee('How to Create Product Bundles on Shopify')->assertSee('Build Your Own Box on Shopify', false);
        $page->assertSee('/blog/how-to-run-ab-test-shopify', false)->assertSee('min read');

        $this->get('/blog/how-to-run-ab-test-shopify')->assertOk()
            ->assertSee('How to Run a Valid A/B Test on Shopify')
            ->assertSee('<h2 id="3-get-enough-data">', false)        // sections get anchors
            ->assertSee('href="#3-get-enough-data"', false)          // and an "On this page" link
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee(route('site.feature', 'ab-testing'), false)  // related feature
            ->assertSee('More articles');
        $this->get('/sitemap.xml')->assertSee(route('site.blog.post', 'subscription-bundles-shopify'), false);
        $this->get('/blog/no-such-post')->assertNotFound();
    }

    public function test_drafts_and_scheduled_articles_stay_hidden(): void
    {
        BlogPost::create(['slug' => 'secret-draft', 'title' => 'Secret draft', 'body' => 'Hidden', 'status' => 'draft']);
        BlogPost::create(['slug' => 'next-week', 'title' => 'Next week', 'body' => 'Soon', 'status' => 'published', 'published_at' => now()->addWeek()]);
        $this->get('/blog')->assertDontSee('Secret draft')->assertDontSee('Next week');
        $this->get('/blog/secret-draft')->assertNotFound();
        $this->get('/blog/next-week')->assertNotFound();
        $this->travel(8)->days();
        $this->get('/blog/next-week')->assertOk()->assertSee('Soon');
    }

    public function test_admins_write_preview_publish_and_delete_articles(): void
    {
        $admin = $this->admin('operations');
        $this->actingAs($admin)->get('/admin/blog')->assertOk()->assertSee('What Is Shopify CRO?', false)->assertSee('Live (12)');

        // A new article is a draft until published; the address comes from the title.
        $this->actingAs($admin)->post('/admin/blog', ['title' => 'Holiday Bundles That Sell', 'body' => "## Plan early\n\nStart in October.", 'category' => 'Guides', 'feature' => 'bundles', 'intent' => 'save'])
            ->assertRedirect()->assertSessionHas('status', 'Saved as a draft. It stays hidden until you publish it.');
        $post = BlogPost::where('slug', 'holiday-bundles-that-sell')->sole();
        $this->get('/blog/holiday-bundles-that-sell')->assertNotFound();

        $this->actingAs($admin)->post('/admin/blog/preview', ['title' => 'Holiday Bundles That Sell', 'body' => "## Plan early\n\n<script>alert(1)</script>Start in October."])
            ->assertOk()->assertSee('Preview: this is how the article will look')->assertSee('<h2 id="plan-early">', false)->assertDontSee('<script>alert(1)</script>', false);

        $this->actingAs($admin)->post("/admin/blog/{$post->id}", ['title' => 'Holiday Bundles That Sell', 'slug' => 'holiday-bundles', 'body' => $post->body, 'intent' => 'publish', 'featured' => '1'])
            ->assertRedirect()->assertSessionHas('status', 'Saved. The article is live on the blog.');
        $this->get('/blog/holiday-bundles')->assertOk()->assertSee('Start in October.');
        $this->get('/blog')->assertSeeInOrder(['Holiday Bundles That Sell', 'Subscription Bundles on Shopify']); // featured first

        // Addresses are unique; unpublishing hides it again; deleting removes it.
        $this->actingAs($admin)->post('/admin/blog', ['title' => 'Holiday Bundles', 'body' => 'x', 'intent' => 'save'])->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post("/admin/blog/{$post->id}", ['title' => 'Holiday Bundles That Sell', 'slug' => 'holiday-bundles', 'body' => $post->body, 'intent' => 'unpublish']);
        $this->get('/blog/holiday-bundles')->assertNotFound();
        $this->actingAs($admin)->post("/admin/blog/{$post->id}/delete")->assertRedirect('/admin/blog');
        $this->assertNull(BlogPost::find($post->id));
    }

    public function test_support_agents_cannot_edit_the_blog(): void
    {
        $this->actingAs($this->admin('support'))->get('/admin/blog')->assertForbidden();
    }
}
