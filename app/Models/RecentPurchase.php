<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A product from a real completed order, shown by Sales pop. No personal data.
 */
class RecentPurchase extends Model
{
    public $timestamps = false;

    public const KEEP = 100;

    public const MAX_DAYS = 30;

    protected $fillable = ['store_id', 'order_ref', 'product_id', 'title', 'url', 'image', 'country', 'purchased_at'];

    protected function casts(): array
    {
        return ['purchased_at' => 'datetime'];
    }
}
