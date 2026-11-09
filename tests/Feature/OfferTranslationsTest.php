<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\FeatureFlag;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Translations\OfferTranslations;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Widget translations (G22). */
class OfferTranslationsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public function test_only_shopper_facing_text_is_offered_for_translation(): void
    {
        $texts = OfferTranslations::texts([
            'headline' => 'Bundle and save', 'cta_text' => 'Add to cart', 'color' => '#ff0000', 'url' => 'https://x.test',
            'products' => [['id' => 'gid://shopify/Product/1', 'title' => 'Serum']], 'words' => ['free' => 'FREE', 'value' => '({amount} Value)'],
            'offers' => [['title' => 'Buy 2', 'badge' => 'Most popular', 'quantity' => '2']], 'tz' => 'UTC', 'image' => 'https://cdn/x.png',
        ]);
        $this->assertSame(['headline' => 'Bundle and save', 'cta_text' => 'Add to cart', 'words.free' => 'FREE', 'words.value' => '({amount} Value)',
            'offers.0.title' => 'Buy 2', 'offers.0.badge' => 'Most popular'], $texts);
    }

    public function test_a_translation_shows_until_the_original_changes(): void
    {
        $t = app(OfferTranslations::class);
        $experiences = [['id' => 'bundle-1', 'content' => ['headline' => 'Bundle and save', 'cta_text' => 'Add to cart']]];
        $map = ['bundle-1' => ['fr' => ['headline' => ['Bundle and save', 'Achetez en lot'], 'cta_text' => ['Add', 'Ajouter']]]];
        $fr = $t->apply($experiences, $map, 'fr');
        $this->assertSame('Achetez en lot', $fr[0]['content']['headline']);
        $this->assertSame('Add to cart', $fr[0]['content']['cta_text'], 'The original changed since: the default shows.');
        $this->assertSame($experiences, $t->apply($experiences, $map, 'de'), 'No translation: unchanged.');
    }

    public function test_each_store_language_gets_its_own_copy_and_old_ones_are_removed(): void
    {
        $this->setUpShopify();
        foreach (['translations'] as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
        $store = $this->installedStore(['plan' => 'growth', 'scopes' => config('shopify.scopes').',read_locales']);
        $sets = [];
        $deletes = [];
        Http::fake(function (Request $r) use (&$sets, &$deletes) {
            $q = $r['query'] ?? '';
            if (str_contains($q, 'shopLocales')) {
                return Http::response(['data' => ['shopLocales' => [['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true], ['locale' => 'fr', 'name' => 'French', 'primary' => false, 'published' => true]]]]);
            }
            if (str_contains($q, 'currentAppInstallation')) {
                return Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => null]]]);
            }
            if (str_contains($q, 'metafieldsSet')) {
                $sets[] = array_column(json_decode(json_encode($r['variables']), true)['metafields'], 'key');

                return Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]);
            }
            if (str_contains($q, 'metafieldsDelete')) {
                $deletes[] = array_column(json_decode(json_encode($r['variables']), true)['m'], 'key');
            }

            return Http::response(['data' => []]);
        });

        app(StorefrontPublisher::class)->publish($store);
        $this->assertSame(['experiences', 'experiences_fr'], $sets[0]);
        $this->assertSame(['fr'], $store->fresh()->capability('translated_languages'));

        // Translations turned off: the French copy is removed.
        FeatureFlag::where('key', 'translations')->delete();
        Features::forget();
        app(StorefrontPublisher::class)->publish($store->fresh());
        $this->assertSame(['experiences'], $sets[1]);
        $this->assertSame([['experiences_fr']], $deletes);
    }
}
