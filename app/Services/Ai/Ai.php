<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Models\Ai\AiProvider;
use App\Models\Store;
use App\Support\AiSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Growvia's AI features (Morning Brief, offer ideas) through the providers set in Internal Admin ›
 * AI providers, tried in order. A provider is skipped when the request would go over one of its
 * limits (requests a minute or day, tokens a day or month), and when it fails or answers with a
 * rate limit it rests; either way the next provider answers.
 * Every request:
 * - carries only what it needs (totals and product names; never customer names, emails or addresses);
 * - asks for JSON matching a schema, checked before use;
 * - is recorded with its provider, tokens and cost, against each store's monthly budget and request cap.
 * ANTHROPIC_API_KEY on Railway still works on its own when no provider is added.
 */
class Ai
{
    public function providers()
    {
        $list = AiProvider::where('is_active', true)->orderBy('priority')->orderBy('id')->get();
        if ($list->isEmpty() && (string) config('services.anthropic.key') !== '') {
            $list = collect([new AiProvider(['name' => 'Anthropic Claude (Railway variable)', 'preset' => 'anthropic', 'driver' => 'anthropic',
                'model' => (string) config('services.anthropic.model'), 'credentials' => ['api_key' => (string) config('services.anthropic.key')], 'price_in' => 4, 'price_out' => 20, 'is_active' => true])]);
        }

        return $list;
    }

    public function configured(): bool
    {
        return AiSettings::get()['enabled'] && $this->providers()->isNotEmpty();
    }

    /** This month's AI budget for the store in USD (0 = none: AI features are Premium). */
    public function budget(Store $store): float
    {
        return $store->effectivePlan() === 'premium' ? (float) AiSettings::get()['budget_premium'] : 0.0;
    }

    public function spent(Store $store): float
    {
        return (float) DB::table('ai_usage')->where('store_id', $store->id)->where('created_at', '>=', now()->startOfMonth())->sum('cost');
    }

    public function requests(Store $store): int
    {
        return DB::table('ai_usage')->where('store_id', $store->id)->where('created_at', '>=', now()->startOfMonth())->count();
    }

    public function available(Store $store): bool
    {
        return $this->configured() && $this->budget($store) > 0 && $this->spent($store) < $this->budget($store)
            && $this->requests($store) < (int) AiSettings::get()['requests_per_store'] && $this->hasCapacity();
    }

    /** Whether any provider can take a request now (not off, resting or at a limit). */
    public function hasCapacity(): bool
    {
        return $this->providers()->contains(fn (AiProvider $p) => $p->usable() && $p->limitReached() === null);
    }

    /** Rough tokens for a request (about 4 characters a token, plus the most it may write). */
    public static function estimate(string $system, string $prompt, array $schema, int $maxTokens): int
    {
        return (int) ceil((mb_strlen($system) + mb_strlen($prompt) + strlen(json_encode($schema))) / 4) + $maxTokens;
    }

    /** One request returning JSON matching $schema, or null (no AI, over budget, or every provider failed). */
    public function json(Store $store, string $feature, string $system, string $prompt, array $schema, int $maxTokens = 4000): ?array
    {
        if (! $this->available($store)) {
            return null;
        }
        $estimate = self::estimate($system, $prompt, $schema, $maxTokens);
        foreach ($this->providers() as $p) {
            if (! $p->usable() || $p->limitReached($estimate) !== null) {
                continue;
            }
            try {
                [$data, $in, $out, $model] = $this->call($p, $system, $prompt, $schema, $maxTokens);
            } catch (Throwable $e) {
                $this->failed($p, $e);
                $this->record($store, $feature, $p, $p->model, 0, 0, 'failed');

                continue;
            }
            $this->record($store, $feature, $p, $model, $in, $out, $data === null ? 'refused' : 'ok');
            if ($p->exists) {
                $month = now()->format('Y-m');
                $p->forceFill(['status' => 'ok', 'last_error' => null, 'last_used_at' => now(), 'month' => $month,
                    'requests_month' => ($p->month === $month ? $p->requests_month : 0) + 1])->save();
            }
            if ($data !== null) {
                return $data;
            }
        }

        return null;
    }

    /** "Test" in Internal Admin: a tiny JSON request. Returns an error, or null. */
    public function test(AiProvider $p): ?string
    {
        try {
            [$data] = $this->call($p, 'Answer with JSON only.', 'Reply with {"ok": true}.', ['type' => 'object', 'additionalProperties' => false, 'required' => ['ok'], 'properties' => ['ok' => ['type' => 'boolean']]], 200);
        } catch (Throwable $e) {
            $this->failed($p, $e);

            return mb_substr($e->getMessage(), 0, 300);
        }
        $p->forceFill(['status' => 'ok', 'last_error' => null, 'paused_until' => null])->save();

        return ($data['ok'] ?? null) === true ? null : 'The model answered, but not with the JSON asked for. Try another model.';
    }

    /** [data|null, input tokens, output tokens, model] or an exception. */
    private function call(AiProvider $p, string $system, string $prompt, array $schema, int $maxTokens): array
    {
        $key = (string) ($p->credentials['api_key'] ?? '');
        if ($p->driver === 'anthropic') {
            $message = (new Client(apiKey: $key))->beta->messages->create(
                model: $p->model, maxTokens: $maxTokens, system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                fallbacks: 'default', betas: ['server-side-fallback-2026-07-01'],
            );
            $data = null;
            if (! in_array($message->stopReason, ['refusal', 'max_tokens'], true)) {
                foreach ($message->content as $block) {
                    if ($block->type === 'text') {
                        $data = self::decode($block->text);
                        break;
                    }
                }
            }

            return [$data, (int) $message->usage->inputTokens, (int) $message->usage->outputTokens, (string) ($message->model ?? $p->model)];
        }

        // OpenAI-compatible chat completions (Gemini, Groq, OpenRouter, Mistral, Together, DeepSeek, Ollama…).
        $r = Http::timeout(60)->withToken($key)->acceptJson()->post(rtrim((string) $p->base_url, '/').'/chat/completions', [
            'model' => $p->model, 'max_tokens' => $maxTokens, 'temperature' => 0.4, 'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system."\n\nReply with one JSON object only, no other text, matching this JSON schema:\n".json_encode($schema)],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);
        if ($r->status() === 429) {
            throw new RuntimeException('Rate limit reached (429).', 429);
        }
        if (! $r->successful()) {
            throw new RuntimeException('HTTP '.$r->status().': '.mb_substr((string) ($r->json('error.message') ?? $r->body()), 0, 200));
        }

        return [self::decode((string) $r->json('choices.0.message.content', '')), (int) $r->json('usage.prompt_tokens', 0), (int) $r->json('usage.completion_tokens', 0), (string) ($r->json('model') ?: $p->model)];
    }

    /** JSON from a model's text, also when wrapped in ``` fences. */
    public static function decode(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text)));
        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }

    /** A failing provider rests: 10 minutes after a rate limit, 2 after another error. */
    private function failed(AiProvider $p, Throwable $e): void
    {
        report($e);
        if ($p->exists) {
            $p->forceFill(['status' => 'failing', 'last_error' => mb_substr($e->getMessage(), 0, 500), 'paused_until' => now()->addMinutes($e->getCode() === 429 ? 10 : 2)])->save();
        }
    }

    private function record(Store $store, string $feature, AiProvider $p, string $model, int $in, int $out, string $outcome): void
    {
        DB::table('ai_usage')->insert(['store_id' => $store->id, 'feature' => $feature, 'provider' => mb_substr($p->name, 0, 80), 'provider_id' => $p->id, 'model' => mb_substr($model, 0, 60),
            'input_tokens' => $in, 'output_tokens' => $out, 'cost' => round(($in * $p->price_in + $out * $p->price_out) / 1_000_000, 5), 'outcome' => $outcome, 'created_at' => now()]);
    }
}
