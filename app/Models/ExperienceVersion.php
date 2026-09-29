<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceVersion extends Model
{
    protected $table = 'cro_experience_versions';

    protected $fillable = ['cro_experience_id', 'version', 'template_key', 'config', 'change_note', 'created_by', 'published_at'];

    protected function casts(): array
    {
        return ['config' => 'array', 'published_at' => 'datetime'];
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class, 'cro_experience_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'created_by');
    }
}
