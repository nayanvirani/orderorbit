<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    public const TONES = ['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'critical' => 'Critical'];

    public const PLACEMENTS = ['home' => 'Home page banner', 'every_page' => 'Top of every page'];

    public const AUDIENCES = ['all' => 'All stores', 'plans' => 'Stores on chosen plans', 'module' => 'Stores whose plan includes a module', 'stores' => 'Chosen stores'];

    /** Templates in the Internal Admin; the price change one follows Shopify's 30-day notice rule. */
    public const TEMPLATES = [
        'feature' => ['New: {feature}', 'You can now {what}. Open it from the menu.', 'Try it', 'info'],
        'maintenance' => ['Planned maintenance on {date}', 'Growvia settings are read-only from {time} for about 30 minutes. Your widgets keep working on your store.', null, 'warning'],
        'shopify' => ['Shopify is having an issue', 'Shopify is fixing a problem that affects {what}. Orders are not affected. We\'ll update this when it\'s resolved.', 'Shopify status', 'critical'],
        'price' => ['{plan} plan price changes on {date}', 'From {date} the {plan} plan costs {price} a month. Your plan and settings stay the same, and you can change plan at any time.', 'See plans', 'info'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['plans' => 'array', 'store_ids' => 'array', 'owners_only' => 'boolean', 'dismissible' => 'boolean', 'email_owners' => 'boolean',
            'emailed_at' => 'datetime', 'price_change_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function views(): HasMany
    {
        return $this->hasMany(AnnouncementView::class);
    }

    public function status(): string
    {
        return match (true) {
            $this->starts_at->gt(now()) => 'Scheduled',
            $this->ends_at !== null && $this->ends_at->lte(now()) => 'Ended',
            default => 'Live',
        };
    }

    /** Whether this store (and, for owners-only, this person) is in the audience. */
    public function reaches(Store $store, ?StoreUser $user = null): bool
    {
        if ($this->owners_only && $user !== null && $user->role !== 'owner') {
            return false;
        }

        return match ($this->audience) {
            'plans' => in_array($store->effectivePlan(), $this->plans ?? [], true),
            'module' => $this->module !== null && $store->planIncludes($this->module),
            'stores' => in_array($store->id, array_map('intval', $this->store_ids ?? []), true),
            default => true,
        };
    }
}
