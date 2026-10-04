<?php

namespace App\Models\Automation;

use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRun extends Model
{
    protected $table = 'automation_runs';

    protected $fillable = ['store_id', 'workflow_id', 'version_id', 'trigger', 'idempotency_key', 'subject', 'status', 'step', 'attempts', 'depth', 'context', 'results', 'resume_at', 'error', 'test', 'finished_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'results' => 'array', 'resume_at' => 'datetime', 'finished_at' => 'datetime', 'test' => 'boolean'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'version_id');
    }

    /** How long the run took (or has taken so far), e.g. "3 days 2 minutes". */
    public function duration(): string
    {
        $seconds = (int) $this->created_at->diffInSeconds($this->finished_at ?? now(), true);

        return $seconds < 1 ? 'under a second' : \Carbon\CarbonInterval::seconds($seconds)->cascade()->forHumans(['parts' => 2]);
    }

    public function canRetry(): bool
    {
        return $this->status === 'failed' && ! $this->test;
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowLog::class, 'run_id');
    }
}
