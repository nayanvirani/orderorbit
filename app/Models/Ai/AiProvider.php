<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** An AI service Growvia's AI features can use (Internal Admin › AI providers). */
class AiProvider extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    /** preset => [label, driver, base URL, suggested model, USD in/out per million tokens, note] */
    public const PRESETS = [
        'gemini' => ['Google Gemini (free tier)', 'openai', 'https://generativelanguage.googleapis.com/v1beta/openai', 'gemini-2.5-flash', [0, 0],
            'Key from Google AI Studio (aistudio.google.com › Get API key). On the free tier Google may use prompts to improve its products.'],
        'groq' => ['Groq (free tier)', 'openai', 'https://api.groq.com/openai/v1', 'llama-3.3-70b-versatile', [0, 0],
            'Key from console.groq.com › API keys. Free with rate limits per minute and per day.'],
        'openrouter' => ['OpenRouter (free models)', 'openai', 'https://openrouter.ai/api/v1', 'meta-llama/llama-3.3-70b-instruct:free', [0, 0],
            'Key from openrouter.ai › Keys. Models ending in ":free" cost nothing, with daily limits.'],
        'mistral' => ['Mistral (free tier)', 'openai', 'https://api.mistral.ai/v1', 'mistral-small-latest', [0, 0],
            'Key from console.mistral.ai › API keys (free "Experiment" plan).'],
        'together' => ['Together AI', 'openai', 'https://api.together.xyz/v1', 'meta-llama/Llama-3.3-70B-Instruct-Turbo-Free', [0, 0],
            'Key from api.together.ai › Settings › API keys. Models ending in "-Free" cost nothing.'],
        'deepseek' => ['DeepSeek', 'openai', 'https://api.deepseek.com/v1', 'deepseek-chat', [0.27, 1.1],
            'Key from platform.deepseek.com › API keys. Low cost, not free.'],
        'anthropic' => ['Anthropic Claude', 'anthropic', null, 'claude-opus-5-5', [4, 20],
            'Key from console.anthropic.com › API keys. Paid; the best quality.'],
        'custom' => ['Other (OpenAI-compatible)', 'openai', '', '', [0, 0],
            'Any service with an OpenAI-compatible /chat/completions endpoint, e.g. a self-hosted Ollama at https://your-server/v1.'],
    ];

    /** Limit fields => [label, period]. Empty = no limit. */
    public const LIMITS = [
        'limit_requests_minute' => ['requests a minute', 'minute'],
        'limit_requests_day' => ['requests a day', 'day'],
        'limit_tokens_day' => ['tokens a day', 'day'],
        'limit_tokens_month' => ['tokens a month', 'month'],
    ];

    /**
     * Suggested free-tier limits per preset, from each provider's published limits for the
     * suggested model (they change; the form says to check). Order as LIMITS.
     */
    public const SUGGESTED_LIMITS = [
        'gemini' => [10, 250, null, null],
        'groq' => [30, 1000, 100000, null],
        'openrouter' => [20, 50, null, null],
        'mistral' => [60, null, null, 1000000000],
    ];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'is_active' => 'boolean', 'paused_until' => 'datetime', 'last_used_at' => 'datetime',
            'price_in' => 'float', 'price_out' => 'float', 'limit_requests_minute' => 'integer', 'limit_requests_day' => 'integer',
            'limit_tokens_day' => 'integer', 'limit_tokens_month' => 'integer'];
    }

    public function usable(): bool
    {
        return $this->is_active && (! $this->paused_until || $this->paused_until->isPast());
    }

    /** Use so far: requests this minute and today, tokens today and this month (UTC). */
    public function usage(): array
    {
        if (! $this->exists) {
            return ['minute' => 0, 'requests_day' => 0, 'tokens_day' => 0, 'tokens_month' => 0];
        }
        $rows = fn ($since) => DB::table('ai_usage')->where('provider_id', $this->id)->where('created_at', '>=', $since);
        $day = $rows(now()->startOfDay())->selectRaw('count(*) as n, coalesce(sum(input_tokens + output_tokens), 0) as t')->first();

        return [
            'minute' => $rows(now()->subMinute())->count(),
            'requests_day' => (int) $day->n,
            'tokens_day' => (int) $day->t,
            'tokens_month' => (int) $rows(now()->startOfMonth())->sum(DB::raw('input_tokens + output_tokens')),
        ];
    }

    /**
     * The limit a request of about $tokens would reach, as text ("250 requests a day"), or null
     * when there's room. Growvia moves to the next provider instead of waiting for an error.
     */
    public function limitReached(int $tokens = 0, ?array $usage = null): ?string
    {
        if (! $this->limit_requests_minute && ! $this->limit_requests_day && ! $this->limit_tokens_day && ! $this->limit_tokens_month) {
            return null;
        }
        $u = $usage ?? $this->usage();
        $checks = [
            'limit_requests_minute' => $u['minute'] + 1, 'limit_requests_day' => $u['requests_day'] + 1,
            'limit_tokens_day' => $u['tokens_day'] + max(1, $tokens), 'limit_tokens_month' => $u['tokens_month'] + max(1, $tokens),
        ];
        foreach ($checks as $field => $after) {
            if ($this->{$field} && $after > $this->{$field}) {
                return number_format($this->{$field}).' '.self::LIMITS[$field][0];
            }
        }

        return null;
    }

    public function state(?array $usage = null): string
    {
        return match (true) {
            ! $this->is_active => 'off',
            $this->paused_until && $this->paused_until->isFuture() => 'resting',
            $this->limitReached(0, $usage) !== null => 'limit',
            $this->status === 'failing' => 'failing',
            default => 'ok',
        };
    }
}
