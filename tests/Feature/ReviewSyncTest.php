<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Integrations\StoreIntegration;
use App\Services\Integrations\ReviewSync;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Review apps, step 2 (G30): full reviews from Judge.me and Yotpo. */
class ReviewSyncTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public array $judgeme = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        foreach (['integrations', 'review_apps'] as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
        $this->judgeme = [
            ['id' => 1, 'product_external_id' => 10, 'rating' => 5, 'title' => 'Love it', 'body' => '<p>Great <b>serum</b></p>', 'reviewer' => ['name' => 'Ana Smith', 'email' => 'ana@example.com'], 'published' => true, 'hidden' => false, 'pictures' => [['urls' => ['original' => 'https://judgeme.imgix.net/a.jpg']]], 'created_at' => '2026-10-01T10:00:00Z'],
            ['id' => 2, 'product_external_id' => 10, 'rating' => 3, 'title' => 'Okay', 'body' => 'Fine', 'reviewer' => ['name' => 'Raj'], 'published' => true, 'hidden' => false, 'pictures' => [], 'created_at' => '2026-10-02T10:00:00Z'],
            ['id' => 3, 'product_external_id' => 10, 'rating' => 1, 'title' => 'Hidden', 'body' => 'x', 'reviewer' => ['name' => 'Z'], 'published' => true, 'hidden' => true, 'pictures' => []],
        ];
        Http::fake(function (Request $r) {
            if (str_contains($r->url(), 'judge.me/api/v1/reviews')) {
                if (($r->data()['api_token'] ?? '') === 'bad') {
                    return Http::response([], 401);
                }

                return Http::response(['reviews' => (int) ($r->data()['page'] ?? 1) === 1 ? $this->judgeme : []]);
            }
            if (str_contains($r->url(), 'api.yotpo.com/oauth/token')) {
                return Http::response(['access_token' => 'tok']);
            }
            if (str_contains($r->url(), 'api.yotpo.com/v1/apps/')) {
                return Http::response(['reviews' => (int) ($r->data()['page'] ?? 1) === 1 ? [['id' => 9, 'sku' => '20', 'score' => 4, 'title' => 'Nice', 'content' => 'Good mask', 'name' => 'Lee Wong', 'created_at' => '2026-10-03', 'deleted' => false]] : []]);
            }

            return Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => null], 'metafieldsSet' => ['userErrors' => []]]]);
        });
    }

    public function test_published_reviews_sync_with_first_names_only_and_hidden_ones_left_out(): void
    {
        $store = $this->installedStore(['plan' => 'starter']);
        $owner = $this->member($store, 'owner');
        $this->post('/app/settings/integrations/judgeme/connect', ['api_token' => 'bad'], $this->as($owner))->assertSessionHasErrors('connect');
        $this->post('/app/settings/integrations/judgeme/connect', ['api_token' => 'good'], $this->as($owner))->assertRedirect();

        $rows = DB::table('store_reviews')->orderBy('external_id')->get();
        $this->assertSame(['1', '2'], $rows->pluck('external_id')->all(), 'Hidden reviews are left out.');
        $this->assertSame(['Ana', 'Great serum'], [$rows[0]->author, $rows[0]->body], 'First name only; plain text.');

        // Deleted in Judge.me: gone on the next sync.
        array_shift($this->judgeme);
        app(ReviewSync::class)->sync($store, StoreIntegration::where('provider', 'judgeme')->sole());
        $this->assertSame(['2'], DB::table('store_reviews')->pluck('external_id')->all());
    }

    public function test_the_storefront_gets_reviews_after_the_merchants_filters(): void
    {
        $store = $this->installedStore(['plan' => 'starter']);
        app(ReviewSync::class)->connect($store, 'judgeme', ['api_token' => 'good']);
        app(ReviewSync::class)->connect($store, 'yotpo', ['app_key' => 'k', 'secret' => 's']);

        $data = ReviewSync::forStorefront($store);
        $this->assertSame([3, 5], array_column($data['p']['10'], 0), 'Newest first.');
        $this->assertSame('Yotpo', $data['p']['20'][0][6], 'Each review says where it came from.');
        $this->assertSame('Lee', $data['p']['20'][0][3]);

        StoreIntegration::where('provider', 'judgeme')->update(['settings' => json_encode(['events' => ['four_up' => true, 'photos_only' => false]])]);
        $this->assertSame([5], array_column(ReviewSync::forStorefront($store)['p']['10'], 0), 'Only 4★ and up.');

        $free = $this->installedStore(['shop_domain' => 'f.myshopify.com', 'plan' => 'free']);
        $this->assertNull(ReviewSync::forStorefront($free), 'Starter and up.');
    }
}
