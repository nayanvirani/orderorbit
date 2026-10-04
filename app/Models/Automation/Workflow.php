<?php

namespace App\Models\Automation;

use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $table = 'automation_workflows';

    protected $fillable = ['store_id', 'handle', 'name', 'template', 'status', 'trigger', 'draft', 'has_unpublished_changes', 'published_version_id', 'last_run_at'];

    protected function casts(): array
    {
        return ['draft' => 'array', 'has_unpublished_changes' => 'boolean', 'last_run_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class, 'workflow_id');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'published_version_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class, 'workflow_id');
    }
}
