<?php

namespace App\Models\Profit;

use Illuminate\Database\Eloquent\Model;

/** A variant's cost: set in Growvia, or Shopify's "Cost per item". */
class ProductCost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        // Costs are commercially sensitive: encrypted at rest.
        return ['shopify_cost' => \App\Casts\EncryptedFloat::class, 'cost' => \App\Casts\EncryptedFloat::class, 'price' => 'float', 'previous_price' => 'float', 'weight' => 'float', 'locations' => 'array',
            'tracked' => 'boolean', 'has_image' => 'boolean', 'has_description' => 'boolean', 'price_changed_at' => 'datetime', 'seen_at' => 'datetime'];
    }

    /** The cost used for profit: Growvia's own, else Shopify's. */
    public function effective(): ?float
    {
        return $this->cost ?? $this->shopify_cost;
    }
}
