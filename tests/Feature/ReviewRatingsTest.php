<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Services\Reviews\ProductRatings;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Review stars from review apps (G30 step 1). */
class ReviewRatingsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public function test_ratings_are_read_from_the_standard_and_loox_fields(): void
    {
        $this->assertSame([4.6, 120], ProductRatings::parse(['rating' => ['value' => '{"value":"4.6","scale_min":"1.0","scale_max":"5.0"}'], 'count' => ['value' => '120']]));
        $this->assertSame([4.5, 8], ProductRatings::parse(['rating' => ['value' => '{"value":"9","scale_min":"0","scale_max":"10"}'], 'count' => ['value' => '8']]));
        $this->assertSame([4.8, 33], ProductRatings::parse(['rating' => null, 'count' => null, 'looxRating' => ['value' => '4.8'], 'looxCount' => ['value' => '33']]));
        $this->assertNull(ProductRatings::parse(['rating' => ['value' => '{"value":"5.0","scale_max":"5.0"}'], 'count' => ['value' => '0']]), 'No reviews, no stars.');
        $this->assertNull(ProductRatings::parse(['rating' => null, 'count' => null]));
    }

    public function test_offer_products_get_their_ratings_when_on(): void
    {
        $this->setUpShopify();
        $store = $this->installedStore(['plan' => 'starter']);
        Http::fake(['*/graphql.json' => Http::response(['data' => ['nodes' => [
            ['id' => 'gid://shopify/Product/10', 'rating' => ['value' => '{"value":"4.7","scale_max":"5.0"}'], 'count' => ['value' => '52']],
            ['id' => 'gid://shopify/Product/20', 'rating' => null, 'count' => null],
        ]]])]);
        $data = [['content' => ['products' => [['id' => 'gid://shopify/Product/10'], ['id' => 'gid://shopify/Product/20']]]]];

        $this->assertSame([], app(ProductRatings::class)->forData($store, $data), 'Off without the flag.');
        FeatureFlag::create(['key' => 'review_apps', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->assertSame(['10' => [4.7, 52]], app(ProductRatings::class)->forData($store, $data));
        Http::assertSent(fn ($r) => str_contains($r['query'] ?? '', 'namespace: "reviews", key: "rating"'));
    }
}
