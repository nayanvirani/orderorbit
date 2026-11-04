<?php

namespace App\Models\Profit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An order synced for Profit, amounts in the shop's currency. */
class ProfitOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime', 'returning' => 'boolean', 'excluded' => 'boolean', 'test' => 'boolean', 'cancelled' => 'boolean', 'codes' => 'array',
            'gross' => 'float', 'discounts' => 'float', 'refunded' => 'float', 'shipping' => 'float', 'taxes' => 'float', 'total' => 'float'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProfitOrderLine::class);
    }
}
