<?php

namespace App\Models\Profit;

use Illuminate\Database\Eloquent\Model;

/** Ad spend entered by hand or from a CSV: spread over the days it covers, or monthly when recurring. */
class AdExpense extends Model
{
    public const CHANNELS = ['google' => 'Google', 'meta' => 'Meta (Facebook, Instagram)', 'tiktok' => 'TikTok', 'pinterest' => 'Pinterest', 'influencer' => 'Influencer', 'other' => 'Other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'recurring' => 'boolean', 'amount' => 'float', 'tags' => 'array'];
    }
}
