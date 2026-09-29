<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id', 'actor_type', 'actor_id', 'action', 'entity_type', 'entity_id', 'context', 'request_id',
    ];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public static function record(string $action, ?Store $store = null, array $context = [], string $actorType = 'system', ?string $actorId = null): self
    {
        return static::create([
            'store_id' => $store?->id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'context' => $context ?: null,
            'request_id' => request()?->header('X-Request-Id'),
        ]);
    }
}
