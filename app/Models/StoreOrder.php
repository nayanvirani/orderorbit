<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Shopify order's total, kept to measure the store's sales against its plan. No customer details.
 */
class StoreOrder extends Model
{
    public $timestamps = false;

    /** Order rows are kept for this many 30-day cycles. */
    public const KEEP_CYCLES = 3;

    protected $fillable = ['store_id', 'shopify_order_id', 'amount', 'currency', 'amount_usd', 'test', 'cancelled', 'ordered_at', 'synced_at'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'amount_usd' => 'float', 'test' => 'boolean', 'cancelled' => 'boolean', 'ordered_at' => 'datetime', 'synced_at' => 'datetime'];
    }
}
