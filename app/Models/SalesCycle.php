<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A finished 30-day sales cycle for a store.
 */
class SalesCycle extends Model
{
    protected $fillable = ['store_id', 'starts_at', 'ends_at', 'sales_usd', 'orders_count', 'plan', 'sales_limit', 'over_limit'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'sales_usd' => 'float', 'sales_limit' => 'float', 'over_limit' => 'boolean'];
    }
}
