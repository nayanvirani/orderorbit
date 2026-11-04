<?php

namespace App\Models\Profit;

use Illuminate\Database\Eloquent\Model;

/** One day of a store's profit, recalculated when orders, costs or rules change. */
class ProfitDaily extends Model
{
    protected $table = 'profit_daily';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $money = array_fill_keys(['gross', 'discounts', 'refunded', 'net_sales', 'shipping', 'cogs', 'missing_cost_sales', 'fees', 'shipping_cost',
            'ad_spend', 'fixed_costs', 'gross_profit', 'contribution', 'profit'], 'float');

        return $money; // day stays a Y-m-d string, the same in every database
    }
}
