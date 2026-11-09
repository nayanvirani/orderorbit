<?php

namespace Tests\Feature;

use App\Models\Ai\AiProvider;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\Ai;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Internal Admin › AI providers: free OpenAI-compatible services with failover, budgets and caps. */
class AiProvidersTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private const SCHEMA = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        config(['services.anthropic.key' => '']);
    }

    private function provider(string $preset, int $priority, string $key = 'key-123'): AiProvider
    {
        [$label, $driver, $url, $model] = AiProvider::PRESETS[$preset];

        return AiProvider::create(['name' => $label, 'preset' => $preset, 'driver' => $driver, 'base_url' => $url, 'model' => $model,
            'credentials' => ['api_key' => $key], 'priority' => $priority, 'is_active' => true, 'price_in' => 0, 'price_out' => 0]);
    }

    private function premium(): Store
    {
        return $this->installedStore(['plan' => 'premium']);
    }

    public function test_a_rate_limited_provider_rests_and_the_next_one_answers(): void
    {
        $gemini = $this->provider('gemini', 1);
        $groq = $this->provider('groq', 2);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429),
            'api.groq.com/*' => Http::response(['model' => 'llama-3.3-70b-versatile', 'choices' => [['message' => ['content' => "```json\n{\"ok\": true}\n```"]]],
                'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 5]]),
        ]);
        $store = $this->premium();

        $this->assertSame(['ok' => true], app(Ai::class)->json($store, 'offer_copy', 'System', 'Prompt', self::SCHEMA, 200));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.groq.com/openai/v1/chat/completions') && $r['response_format'] === ['type' => 'json_object']
            && $r->hasHeader('Authorization', 'Bearer key-123') && str_contains($r['messages'][0]['content'], '"boolean"'));
        $this->assertSame('failing', $gemini->fresh()->status);
        $this->assertTrue($gemini->fresh()->paused_until->isFuture());
        $this->assertSame('resting', $gemini->fresh()->state());
        $this->assertSame(1, $groq->fresh()->requests_month);
        $this->assertSame(['failed', 'ok'], DB::table('ai_usage')->orderBy('id')->pluck('outcome')->all());

        // While Gemini rests, it isn't called at all.
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '{"ok": true}']]]])]);
        app(Ai::class)->json($store, 'offer_copy', 'System', 'Prompt', self::SCHEMA, 200);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'generativelanguage'));
    }

    public function test_a_provider_at_its_limit_is_skipped_before_it_errors(): void
    {
        $gemini = $this->provider('gemini', 1);
        $gemini->forceFill(['limit_requests_day' => 2])->save();
        $groq = $this->provider('groq', 2);
        $groq->forceFill(['limit_tokens_day' => 4000])->save();
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"ok": true}']]], 'usage' => ['prompt_tokens' => 1500, 'completion_tokens' => 500]])]);
        $store = $this->premium();
        $ai = app(Ai::class);

        foreach (range(1, 4) as $_) {
            $this->assertSame(['ok' => true], $ai->json($store, 'offer_copy', 'System', 'Prompt', self::SCHEMA, 200));
        }
        // Gemini answers twice (its daily limit), then Groq twice (4,000 tokens, its daily limit).
        $this->assertSame([$gemini->id, $gemini->id, $groq->id, $groq->id], DB::table('ai_usage')->orderBy('id')->pluck('provider_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('limit', $gemini->fresh()->state());
        $this->assertSame('2 requests a day', $gemini->fresh()->limitReached());
        $this->assertSame('4,000 tokens a day', $groq->fresh()->limitReached(2200));
        $this->assertSame('ok', $gemini->fresh()->status); // a limit isn't an error

        // Every provider full: AI pauses (Morning Brief shows paused) instead of failing.
        $this->assertFalse($ai->hasCapacity());
        $this->assertFalse($ai->available($store));
        $this->assertNull($ai->json($store, 'offer_copy', 'System', 'Prompt', self::SCHEMA, 200));
        Http::assertSentCount(4);

        // The next day there's room again.
        $this->travel(1)->days();
        $this->assertTrue($ai->available($store));
    }

    public function test_every_provider_failing_returns_nothing(): void
    {
        $this->provider('gemini', 1);
        Http::fake(['*' => Http::response('down', 500)]);

        $this->assertNull(app(Ai::class)->json($this->premium(), 'offer_copy', 'System', 'Prompt', self::SCHEMA));
    }

    public function test_budgets_request_caps_and_the_switch(): void
    {
        $this->provider('groq', 1);
        $ai = app(Ai::class);
        $premium = $this->premium();
        $this->assertTrue($ai->available($premium));
        $this->assertFalse($ai->available($this->installedStore(['plan' => 'growth', 'shop_domain' => 'other.myshopify.com'])));

        AiSettings::save(['enabled' => true, 'budget_premium' => 5, 'requests_per_store' => 2]);
        foreach ([1, 2] as $_) {
            DB::table('ai_usage')->insert(['store_id' => $premium->id, 'feature' => 'offer_copy', 'model' => 'x', 'input_tokens' => 1, 'output_tokens' => 1, 'cost' => 0, 'outcome' => 'ok', 'created_at' => now()]);
        }
        $this->assertFalse($ai->available($premium));

        AiSettings::save(['enabled' => false, 'budget_premium' => 5, 'requests_per_store' => 300]);
        $this->assertFalse($ai->configured());
    }

    public function test_without_providers_the_railway_key_still_works(): void
    {
        $this->assertFalse(app(Ai::class)->configured());
        config(['services.anthropic.key' => 'sk-ant-test', 'services.anthropic.model' => 'claude-opus-5-5']);
        $this->assertSame('anthropic', app(Ai::class)->providers()->first()->driver);
    }

    public function test_super_admin_adds_tests_orders_and_removes_providers(): void
    {
        $admin = User::forceCreate(['name' => 'Ava', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => 'super_admin']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"ok": true}']]]])]);
        $key = 'gsk_SecretKeyValue9876';

        $this->actingAs($admin)->get('/admin/ai')->assertOk()->assertSee('AI providers');
        $this->actingAs($admin)->get('/admin/ai/providers/new?preset=groq')->assertOk()->assertSee('console.groq.com')
            ->assertSee('Tokens a day')->assertSee('value="100000"', false);
        $this->actingAs($admin)->post('/admin/ai/providers', ['preset' => 'groq', 'name' => 'Groq', 'model' => 'llama-3.3-70b-versatile', 'api_key' => $key,
            'limit_requests_minute' => 30, 'limit_requests_day' => 1000, 'limit_tokens_day' => 100000, 'limit_tokens_month' => ''])
            ->assertRedirect('/admin/ai')->assertSessionHas('status', 'Groq saved and working.');
        $this->actingAs($admin)->post('/admin/ai/providers', ['preset' => 'gemini', 'name' => 'Gemini', 'model' => 'gemini-2.5-flash', 'api_key' => 'AIzaKey'])->assertRedirect('/admin/ai');

        $groq = AiProvider::where('name', 'Groq')->first();
        $this->assertSame([30, 1000, 100000, null], [$groq->limit_requests_minute, $groq->limit_requests_day, $groq->limit_tokens_day, $groq->limit_tokens_month]);
        $this->actingAs($admin)->get('/admin/ai')->assertSee('0 / 1,000 requests a day');
        $this->assertSame($key, $groq->credentials['api_key']);
        $this->assertNotSame($key, DB::table('ai_providers')->where('id', $groq->id)->value('credentials'));
        $this->actingAs($admin)->get('/admin/ai')->assertOk()->assertDontSee($key);
        $this->actingAs($admin)->get("/admin/ai/providers/{$groq->id}")->assertOk()->assertDontSee($key);

        // Editing without a key keeps the saved one.
        $this->actingAs($admin)->post("/admin/ai/providers/{$groq->id}", ['name' => 'Groq main', 'model' => 'llama-3.1-8b-instant', 'api_key' => ''])->assertRedirect('/admin/ai');
        $this->assertSame($key, $groq->fresh()->credentials['api_key']);

        $gemini = AiProvider::where('name', 'Gemini')->first();
        $this->actingAs($admin)->post("/admin/ai/providers/{$gemini->id}/up")->assertRedirect('/admin/ai');
        $this->assertSame(['Gemini', 'Groq main'], AiProvider::orderBy('priority')->pluck('name')->all());

        $this->actingAs($admin)->post('/admin/ai/settings', ['enabled' => '1', 'budget_premium' => 2, 'requests_per_store' => 50])->assertSessionHas('status');
        $this->assertSame(50, AiSettings::get()['requests_per_store']);

        $this->actingAs($admin)->post("/admin/ai/providers/{$gemini->id}/delete")->assertRedirect('/admin/ai');
        $this->assertSame(1, AiProvider::count());
    }
}
