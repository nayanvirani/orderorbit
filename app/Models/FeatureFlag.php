<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    public const MODES = ['off' => 'Off', 'stores' => 'Only chosen stores', 'percent' => 'A share of stores', 'all' => 'Everyone'];

    protected $fillable = ['key', 'description', 'enabled', 'store_ids', 'mode', 'percent', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'store_ids' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'percent' => 'integer'];
    }

    /** Whether the schedule allows the flag right now (no dates means always). */
    public function inSchedule(): bool
    {
        return ($this->starts_at === null || $this->starts_at->lte(now())) && ($this->ends_at === null || $this->ends_at->gt(now()));
    }
}
