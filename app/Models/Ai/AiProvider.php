<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

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

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'is_active' => 'boolean', 'paused_until' => 'datetime', 'last_used_at' => 'datetime',
            'price_in' => 'float', 'price_out' => 'float'];
    }

    public function usable(): bool
    {
        return $this->is_active && (! $this->paused_until || $this->paused_until->isPast());
    }

    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'off',
            $this->paused_until && $this->paused_until->isFuture() => 'resting',
            $this->status === 'failing' => 'failing',
            default => 'ok',
        };
    }
}
