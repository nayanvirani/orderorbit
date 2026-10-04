<?php

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowVersion extends Model
{
    protected $table = 'automation_versions';

    public $timestamps = false;

    protected $fillable = ['workflow_id', 'version', 'definition', 'program', 'created_by', 'published_at'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'program' => 'array', 'published_at' => 'datetime'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }
}
