<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id', 'actor_type', 'actor_id', 'action', 'entity_type', 'entity_id', 'context', 'request_id',
    ];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }

    /**
     * Records an action. The actor is the signed-in staff member when there is one,
     * otherwise the system (webhooks, jobs).
     */
    public static function record(string $action, ?Store $store = null, array $context = [], ?Model $entity = null): self
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $user = $request?->attributes->get('storeUser');

        return static::create([
            'store_id' => $store?->id,
            'actor_type' => $user ? 'store_user' : 'system',
            'actor_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'context' => $context ?: null,
            'request_id' => $request?->header('X-Request-Id'),
        ]);
    }

    /**
     * Access log for customers' personal data (Shopify protected customer data): who opened a
     * page showing names, emails or order details, and when. Merchant staff are the actor;
     * Growvia's internal admins are named in the context.
     */
    public static function viewed(string $what, ?Store $store = null, array $context = [], ?Model $entity = null): self
    {
        $admin = auth()->guard('web')->user();

        return static::record('personal_data.viewed', $store, ['what' => $what] + $context + ($admin ? ['admin' => $admin->email] : []) + ['ip' => request()?->ip()], $entity);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'actor_id');
    }

    public function store(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
