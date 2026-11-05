<?php

namespace App\Models\Protect;

use Illuminate\Database\Eloquent\Model;

/** One check of a monitored page; kept 30 days. */
class UrlCheck extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ok' => 'boolean', 'slow' => 'boolean', 'checked_at' => 'datetime'];
    }
}
