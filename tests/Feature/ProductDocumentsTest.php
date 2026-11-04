<?php

namespace Tests\Feature;

use App\Models\Documents\IssuedDocument;
use App\Models\Documents\ProductDocument;
use App\Models\FeatureFlag;
use App\Services\Documents\ProductDocuments;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G42: documents by product, one per item, with serials, private links that expire and a verify page. */
class ProductDocumentsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(fn (Request $r) => Http::response(['data' => str_contains($r['query'] ?? '', 'nodes(ids')
            ? ['nodes' => [['id' => 'gid://shopify/Product/10', 'tags' => ['Certified-Gem'], 'collections' => ['nodes' => [['id' => 'gid://shopify/Collection/5']]]]]]
            : ['metafieldsSet' => ['userErrors' => []], 'metafieldDefinitionCreate' => ['createdDefinition' => ['id' => 'x'], 'userErrors' => []]]]));
    }

    private function order(): array
    {
        return ['id' => '4821000', 'name' => '#4821', 'created_at' => '2026-10-21T10:00:00Z', 'lines' => [
            ['product_id' => '10', 'variant_id' => '101', 'title' => 'Solitaire Ring', 'variant_title' => 'Size 6', 'quantity' => 2],
            ['product_id' => '20', 'variant_id' => '201', 'title' => 'Gift box', 'variant_title' => '', 'quantity' => 1],
        ]];
    }

    public function test_the_page_needs_the_flag_and_saves_a_certificate(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $this->page('/app/automation/documents', $owner)->assertNotFound();
        FeatureFlag::create(['key' => 'product_documents', 'mode' => 'all', 'enabled' => true]);
        Features::forget();

        $this->page('/app/automation/documents', $owner)->assertOk()->assertJsonPath('component', 'automation/documents');
        $data = ['name' => 'Certificate of Authenticity', 'kind' => 'certificate', 'match' => 'products', 'per' => 'item',
            'match_items' => [['id' => 'gid://shopify/Product/10', 'title' => 'Solitaire Ring']], 'certificate' => ['serial_prefix' => 'LJ', 'show_name' => true, 'show_qr' => true]];
        $this->send('/app/automation/documents', ['data' => json_encode(['match_items' => []] + $data)], $owner)->assertStatus(422)->assertJsonPath('errors.match_items.0', 'Choose at least one product.');
        $this->post('/app/automation/documents', ['data' => json_encode($data)], $this->as($owner))->assertRedirectContains('document_saved');
        $doc = ProductDocument::sole();
        $this->assertSame('LJ', $doc->certificateSettings()['serial_prefix']);

        $preview = $this->get("/app/automation/documents/{$doc->id}/preview", $this->as($owner))->assertOk();
        $this->assertStringStartsWith('%PDF', $preview->getContent());
    }

    public function test_an_order_gets_one_certificate_per_item_with_serials_links_and_a_verify_page(): void
    {
        $store = $this->installedStore();
        $doc = ProductDocument::create(['store_id' => $store->id, 'name' => 'Certificate', 'kind' => 'certificate', 'match' => 'products', 'per' => 'item',
            'match_items' => [['id' => 'gid://shopify/Product/10', 'title' => 'Solitaire Ring']], 'certificate' => ['serial_prefix' => 'lj']]);
        $guide = ProductDocument::create(['store_id' => $store->id, 'name' => 'Care guide', 'kind' => 'file', 'match' => 'tag', 'match_tag' => 'certified-gem', 'per' => 'order',
            'file_name' => 'care.pdf', 'file_size' => 9, 'file_data' => base64_encode('%PDF-1.4 x')]);
        ProductDocument::create(['store_id' => $store->id, 'name' => 'Other', 'kind' => 'file', 'match' => 'collection', 'per' => 'line',
            'match_items' => [['id' => 'gid://shopify/Collection/99']], 'file_data' => base64_encode('%PDF')]);

        $documents = app(ProductDocuments::class);
        $issued = $documents->forOrder($store, $this->order(), ['name' => 'Priya Sharma']);
        $this->assertSame(['LJ-4821-01', 'LJ-4821-02', null], $issued->pluck('serial')->all(), '2 rings = 2 certificates; the tagged guide once per order; the other collection none.');
        $this->assertSame('Priya Sharma', $issued[0]->customer_name);
        $this->assertSame($guide->id, $issued[2]->product_document_id);

        // Again (a retry or a second email): the same documents.
        $this->assertSame($issued->pluck('id')->all(), $documents->forOrder($store, $this->order(), ['name' => 'Priya Sharma'])->pluck('id')->all());
        $this->assertSame(3, IssuedDocument::count());
        Http::assertSent(fn (Request $r) => str_contains($r['query'] ?? '', 'metafieldsSet') && str_contains(json_encode($r['variables'] ?? []), 'Order/4821000'));

        // The link works for 30 days, then says it expired.
        $this->get('/documents/'.$issued[0]->token)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/documents/'.$issued[2]->token)->assertOk();
        $this->assertSame('%PDF-1.4 x', $this->get('/documents/'.$issued[2]->token)->getContent());
        $this->travel(31)->days();
        $this->get('/documents/'.$issued[0]->token)->assertStatus(410)->assertSee('This link has expired');
        $this->travelBack();

        // The QR code's page confirms it without the customer's name.
        $this->get('/verify/'.$issued[0]->verify_code)->assertOk()->assertSee('Genuine certificate')->assertSee('LJ-4821-01')->assertSee('Solitaire Ring')->assertDontSee('Priya');
        $this->get('/verify/'.$issued[2]->verify_code)->assertNotFound();
        $this->get('/verify/nope')->assertNotFound();
    }

    public function test_orders_without_matching_products_get_nothing(): void
    {
        $store = $this->installedStore();
        ProductDocument::create(['store_id' => $store->id, 'name' => 'Certificate', 'kind' => 'certificate', 'match' => 'products', 'per' => 'item',
            'match_items' => [['id' => 'gid://shopify/Product/77']]]);
        $this->assertCount(0, app(ProductDocuments::class)->forOrder($store, $this->order(), null));
        Http::assertNothingSent();
    }
}
