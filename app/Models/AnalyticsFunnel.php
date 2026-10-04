<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsFunnel extends Model
{
    protected $fillable = ['store_id', 'name', 'steps', 'within'];

    protected function casts(): array
    {
        return ['steps' => 'array'];
    }
}
