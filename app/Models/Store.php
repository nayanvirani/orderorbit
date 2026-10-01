<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class Store extends Model
{
    protected $fillable = [
        'shop_domain', 'access_token', 'refresh_token', 'access_token_expires_at', 'scopes',
        'name', 'email', 'currency', 'timezone', 'shopify_plan', 'theme_name', 'capabilities', 'capabilities_checked_at', 'plan', 'plan_expires_at', 'goal',
        'onboarding_completed_at', 'installed_at', 'uninstalled_at', 'cart_transform_id', 'pixel_token', 'web_pixel_id',
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
            'plan_expires_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
            'sales_checked_at' => 'datetime',
            'over_limit_since' => 'datetime',
            'offers_suspended_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(StoreUser::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
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
     * The plan whose features and limits apply right now: the subscribed plan
     * (including a cancelled plan's paid-up grace period), or the configured
     * plan for our own test shops. Null means no access.
     */
    public function effectivePlan(): ?string
    {
        if ($this->plan !== null && ($this->plan_expires_at === null || $this->plan_expires_at->isFuture())) {
            return $this->plan;
        }

        return $this->isTestShop() ? config('shopify.test_shop_plan') : null;
    }

    public function hasPlanAccess(): bool
    {
        return $this->effectivePlan() !== null;
    }

    public function isTestShop(): bool
    {
        return in_array($this->shop_domain, config('shopify.test_shops', []), true);
    }

    /**
     * Shopify's hosted plan picker (Managed Pricing).
     */
    public function pricingUrl(): string
    {
        return $this->adminUrl('charges/'.(Cache::get('shopify.app_handle') ?: config('shopify.app_handle')).'/pricing_plans');
    }

    /**
     * The plan's limit on total store sales over the last 30 days (USD); null means unlimited.
     * Our own test shops are never limited.
     */
    public function salesLimit(): ?float
    {
        $limit = $this->isTestShop() ? null : config('shopify.billing.plans.'.$this->effectivePlan().'.sales_limit');

        return $limit === null ? null : (float) $limit;
    }

    /**
     * Offers are paused because the store stayed over its plan's sales limit past the grace period.
     */
    public function offersSuspended(): bool
    {
        return $this->offers_suspended_at !== null;
    }

    /**
     * Plan limit for a usage meter; null means unlimited, 0 when there is no plan.
     */
    public function planLimit(string $meter): ?int
    {
        $limits = config('shopify.billing.plans.'.$this->effectivePlan().'.limits');

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

    public function hasScope(string $scope): bool
    {
        $granted = array_filter(explode(',', (string) $this->scopes));

        return in_array($scope, $granted, true) || in_array(str_replace('read_', 'write_', $scope), $granted, true);
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

    /**
     * The Theme Editor link for placing an experience type: the app embed for global types,
     * otherwise "add block" on the matching template.
     */
    public function themeEditorUrl(string $surface): string
    {
        $key = config('shopify.api_key');

        return $surface === 'global'
            ? $this->adminUrl("themes/current/editor?context=apps&activateAppId={$key}/app-embed")
            : $this->adminUrl('themes/current/editor?template='.($surface === 'product' ? 'product' : ($surface === 'cart' ? 'cart' : 'index'))."&addAppBlockId={$key}/experience&target=newAppsSection");
    }
}
