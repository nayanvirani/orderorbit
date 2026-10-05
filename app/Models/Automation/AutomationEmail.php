<?php

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Model;

/** An email a workflow prepared; sent once an email provider is set up in super admin. */
class AutomationEmail extends Model
{
    protected $table = 'automation_emails';

    protected $fillable = ['store_id', 'run_id', 'customer_id', 'to_email', 'subject', 'body', 'status', 'provider', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'next_attempt_at' => 'datetime'];
    }
}
