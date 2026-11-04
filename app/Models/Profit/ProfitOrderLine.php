<?php

namespace App\Models\Profit;

use Illuminate\Database\Eloquent\Model;

/** One line of a synced order. */
class ProfitOrderLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'float', 'discount' => 'float', 'refunded' => 'float'];
    }
}
