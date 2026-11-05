<?php

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Model;

/** An email a workflow prepared; sent once an email provider is set up in super admin. */
class AutomationEmail extends Model
{
    protected $table = 'automation_emails';

    protected $fillable = ['store_id', 'run_id', 'customer_id', 'to_email', 'subject', 'body', 'status', 'provider', 'error', 'sent_at', 'documents', 'documents_as'];

    protected function casts(): array
    {
        // Customer personal data, encrypted at rest.
        return ['to_email' => 'encrypted', 'subject' => 'encrypted', 'body' => 'encrypted', 'sent_at' => 'datetime', 'next_attempt_at' => 'datetime', 'documents' => 'array'];
    }
}
