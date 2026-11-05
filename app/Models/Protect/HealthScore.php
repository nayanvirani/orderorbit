<?php

namespace App\Models\Protect;

use Illuminate\Database\Eloquent\Model;

/** The store's Health Score on a day (G12), for the 8-week trend. */
class HealthScore extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['categories' => 'array', 'score' => 'integer'];
    }
}
