<?php

namespace App\Models\Profit;

use Illuminate\Database\Eloquent\Model;

/** A store's cost rules: payment fees, shipping cost, fixed monthly costs, default cost %. */
class ProfitSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fees' => 'array', 'shipping' => 'array', 'fixed_costs' => 'array', 'protect' => 'array', 'default_cost_percent' => 'float',
            'synced_at' => 'datetime', 'costs_synced_at' => 'datetime', 'backfilled_at' => 'datetime'];
    }

    public static function for(int $storeId): self
    {
        return self::firstOrCreate(['store_id' => $storeId], [
            // Shopify Payments' usual online rate as a starting point; merchants change it.
            'fees' => ['percent' => 2.9, 'fixed' => 0.30, 'countries' => []],
            'shipping' => ['mode' => 'none', 'value' => 0],
            'fixed_costs' => [],
        ]);
    }
}
