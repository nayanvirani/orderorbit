<?php

namespace App\Models\Audiences;

use App\Models\Experience;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalizationRule extends Model
{
    protected $fillable = ['store_id', 'experience_id', 'name', 'position', 'enabled', 'segments', 'conditions', 'outcome', 'template_key'];

    protected function casts(): array
    {
        return ['segments' => 'array', 'conditions' => 'array', 'enabled' => 'boolean'];
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }
}
