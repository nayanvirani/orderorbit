<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    protected $fillable = [
        'shop_domain', 'access_token', 'refresh_token', 'access_token_expires_at', 'scopes',
        'name', 'email', 'currency', 'timezone', 'shopify_plan', 'theme_name', 'capabilities', 'capabilities_checked_at', 'plan', 'goal',
        'onboarding_completed_at', 'installed_at', 'uninstalled_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'capabilities' => 'array',
            'capabilities_checked_at' => 'datetime',
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

    /**
     * Plan limit for a usage meter; null means unlimited, 0 when there is no plan.
     */
    public function planLimit(string $meter): ?int
    {
        $limits = config("shopify.billing.plans.{$this->plan}.limits");

        return is_array($limits) && array_key_exists($meter, $limits) ? $limits[$meter] : 0;
    }

    /**
     * Required scopes that Shopify has not granted. write_x implies read_x.
     *
     * @return list<string>
     */
    public function missingScopes(): array
    {
        $granted = array_filter(explode(',', (string) $this->scopes));
        $required = array_filter(explode(',', (string) config('shopify.scopes')));

        return array_values(array_filter($required, fn ($scope) => ! in_array($scope, $granted, true)
            && ! in_array(str_replace('read_', 'write_', $scope), $granted, true)));
    }

    public function capability(string $key): mixed
    {
        return $this->capabilities[$key] ?? null;
    }

    public function adminUrl(string $path = ''): string
    {
        return 'https://admin.shopify.com/store/'.$this->handle().($path !== '' ? '/'.ltrim($path, '/') : '');
    }

    public function activeOwners(): HasMany
    {
        return $this->users()->where('role', 'owner')->whereNull('disabled_at')->whereNotNull('shopify_user_id');
    }
}
