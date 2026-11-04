<?php

namespace App\Models\Profit;

use Illuminate\Database\Eloquent\Model;

/** A variant's cost: set in Growvia, or Shopify's "Cost per item". */
class ProductCost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['shopify_cost' => 'float', 'cost' => 'float'];
    }

    /** The cost used for profit: Growvia's own, else Shopify's. */
    public function effective(): ?float
    {
        return $this->cost ?? $this->shopify_cost;
    }
}
