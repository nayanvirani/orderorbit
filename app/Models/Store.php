<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    protected $fillable = [
        'shop_domain', 'access_token', 'refresh_token', 'access_token_expires_at', 'scopes',
        'name', 'email', 'currency', 'timezone', 'shopify_plan', 'plan', 'goal',
        'onboarding_completed_at', 'installed_at', 'uninstalled_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(StoreUser::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'ACTIVE')->latestOfMany();
    }

    public function isInstalled(): bool
    {
        return $this->access_token !== null && $this->uninstalled_at === null;
    }

    public function needsTokenRefresh(): bool
    {
        return $this->access_token_expires_at !== null && $this->access_token_expires_at->subMinutes(5)->isPast();
    }

    public function handle(): string
    {
        return str_replace('.myshopify.com', '', $this->shop_domain);
    }

    public function planLimit(string $meter): ?int
    {
        return config("shopify.billing.plans.{$this->plan}.limits.{$meter}", 0);
    }
}
