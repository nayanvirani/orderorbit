<?php

namespace App\Models\Experiments;

use App\Models\StoreUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperimentLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['experiment_id', 'store_user_id', 'message', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'store_user_id');
    }
}
