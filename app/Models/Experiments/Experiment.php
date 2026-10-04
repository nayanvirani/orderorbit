<?php

namespace App\Models\Experiments;

use App\Models\Experience;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experiment extends Model
{
    protected $fillable = ['store_id', 'experience_id', 'handle', 'name', 'hypothesis', 'status', 'audience', 'primary_metric', 'secondary_metrics', 'guardrails',
        'min_days', 'min_visitors', 'min_conversions', 'ends_at', 'started_at', 'ended_at', 'result', 'winner_key'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'secondary_metrics' => 'array', 'guardrails' => 'array', 'ends_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ExperimentVariant::class)->orderBy('key');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ExperimentLog::class)->latest('id');
    }

    public function isLive(): bool
    {
        return $this->status === 'running';
    }

    /** Days since launch (to the end, once it has ended). */
    public function daysRunning(): float
    {
        return $this->started_at ? round($this->started_at->diffInSeconds($this->ended_at ?? now(), true) / 86400, 1) : 0.0;
    }

    public function log(string $message, $user = null): void
    {
        $this->logs()->create(['message' => mb_substr($message, 0, 300), 'store_user_id' => $user?->id, 'created_at' => now()]);
    }
}
