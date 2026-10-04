<?php

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Model;

/** A task or notification a workflow created for the store's team. */
class InboxItem extends Model
{
    protected $table = 'automation_inbox';

    protected $fillable = ['store_id', 'run_id', 'kind', 'title', 'body', 'due_at', 'done_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'done_at' => 'datetime'];
    }
}
