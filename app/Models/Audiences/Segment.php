<?php

namespace App\Models\Audiences;

use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    protected $fillable = ['store_id', 'name', 'description', 'template', 'match', 'rules', 'member_count', 'counted_at', 'archived_at'];

    protected function casts(): array
    {
        return ['rules' => 'array', 'counted_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
}
