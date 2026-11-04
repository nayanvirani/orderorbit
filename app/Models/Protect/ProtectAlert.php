<?php

namespace App\Models\Protect;

use Illuminate\Database\Eloquent\Model;

/** A money-leak alert (G7): open, snoozed, muted or closed once its cause is gone. */
class ProtectAlert extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cost' => 'float', 'opened_at' => 'datetime', 'last_seen_at' => 'datetime', 'snoozed_until' => 'datetime', 'closed_at' => 'datetime', 'emailed_at' => 'datetime'];
    }
}
