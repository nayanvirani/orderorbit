<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id', 'experience_handle', 'event', 'quantity', 'value', 'currency', 'order_ref', 'occurred_at', 'label',
        'name', 'visitor_id', 'session_id', 'customer_id', 'experience_type', 'template', 'page_type', 'product_id', 'variant_id',
        'device', 'country', 'source', 'medium', 'campaign', 'properties', 'experiment_handle', 'variant',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'value' => 'float', 'quantity' => 'integer', 'properties' => 'array'];
    }
}
