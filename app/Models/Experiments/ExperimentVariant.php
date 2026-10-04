<?php

namespace App\Models\Experiments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperimentVariant extends Model
{
    public $timestamps = false;

    protected $fillable = ['experiment_id', 'key', 'name', 'allocation', 'hidden', 'template_key', 'content', 'design'];

    protected function casts(): array
    {
        return ['content' => 'array', 'design' => 'array', 'hidden' => 'boolean', 'allocation' => 'integer'];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    public function isControl(): bool
    {
        return $this->key === 'A';
    }
}
