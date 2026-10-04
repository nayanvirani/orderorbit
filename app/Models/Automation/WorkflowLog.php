<?php

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Model;

class WorkflowLog extends Model
{
    protected $table = 'automation_logs';

    public $timestamps = false;

    protected $fillable = ['run_id', 'step', 'kind', 'status', 'message', 'data', 'created_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'datetime'];
    }
}
