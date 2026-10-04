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

    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowLog::class, 'run_id');
    }
}
