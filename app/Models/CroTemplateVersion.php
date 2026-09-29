<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CroTemplateVersion extends Model
{
    protected $fillable = ['cro_template_id', 'version', 'style', 'schema', 'defaults', 'checksum', 'published_at'];

    protected function casts(): array
    {
        return ['schema' => 'array', 'defaults' => 'array', 'published_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CroTemplate::class, 'cro_template_id');
    }
}
