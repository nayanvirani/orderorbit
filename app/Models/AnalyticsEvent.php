<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['store_id', 'experience_handle', 'event', 'quantity', 'value', 'currency', 'order_ref', 'occurred_at', 'label'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'value' => 'float', 'quantity' => 'integer'];
    }
}
