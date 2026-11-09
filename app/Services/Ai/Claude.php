<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Claude for Growvia's AI features. One structured (JSON schema) request at a time, with:
 * - only the store data the request needs (totals and product names; never customer names,
 *   emails or addresses), not used for training;
 * - each store's monthly AI budget (its plan's), checked before and recorded after every call;
 * - the server-side refusal fallback, and a declined request answered with nothing.
 */
class Claude
{
    /** USD per million tokens [input, output] for the configured model (Claude Opus 5.5). */
    public const PRICE = [4.0, 20.0];

    public function configured(): bool
    {
        return (string) config('services.anthropic.key') !== '';
    }

    /** This month's AI budget for the store in USD (0 = none). */
    public function budget(Store $store): float
    {
        return (float) (config('services.anthropic.budget')[$store->effectivePlan()] ?? 0);
    }

    public function spent(Store $store): float
    {
        return (float) DB::table('ai_usage')->where('store_id', $store->id)->where('created_at', '>=', now()->startOfMonth())->sum('cost');
    }

    /** Whether the store can make an AI request now (configured, has a budget, not used up). */
    public function available(Store $store): bool
    {
        return $this->configured() && $this->budget($store) > 0 && $this->spent($store) < $this->budget($store);
    }

    /**
     * One request returning JSON that matches $schema, or null (no budget, declined, or failed).
     *
     * @param  array<string, mixed>  $schema  a JSON schema object (additionalProperties false, required set)
     */
    public function json(Store $store, string $feature, string $system, string $prompt, array $schema, int $maxTokens = 4000): ?array
    {
        if (! $this->available($store)) {
            return null;
        }
        $model = (string) config('services.anthropic.model');
        try {
            $client = new Client(apiKey: (string) config('services.anthropic.key'));
            $message = $client->beta->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                // A policy decline on the requested model is retried on its default fallback.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (Throwable $e) {
            report($e);
            $this->record($store, $feature, $model, 0, 0, 'failed');

            return null;
        }
        $refused = $message->stopReason === 'refusal';
        $this->record($store, $feature, (string) ($message->model ?? $model), (int) $message->usage->inputTokens, (int) $message->usage->outputTokens, $refused ? 'refused' : 'ok');
        if ($refused || $message->stopReason === 'max_tokens') {
            return null;
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                return is_array($data) ? $data : null;
            }
        }

        return null;
    }

    private function record(Store $store, string $feature, string $model, int $in, int $out, string $outcome): void
    {
        DB::table('ai_usage')->insert(['store_id' => $store->id, 'feature' => $feature, 'model' => mb_substr($model, 0, 60), 'input_tokens' => $in, 'output_tokens' => $out,
            'cost' => round(($in * self::PRICE[0] + $out * self::PRICE[1]) / 1_000_000, 5), 'outcome' => $outcome, 'created_at' => now()]);
    }
}
