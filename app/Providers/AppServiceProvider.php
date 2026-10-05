<?php

namespace App\Providers;

use App\Experiences\Registry;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Shopify\SessionToken;
use App\Services\Usage;
use App\Support\Content;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Usage::class);

        $this->app->singleton(SessionToken::class, fn () => new SessionToken(
            (string) config('shopify.api_key'),
            (string) config('shopify.api_secret'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Plans and platform settings edited in the Internal Admin override config.
        \Illuminate\Support\Facades\Mail::extend('orderorbit', fn () => new \App\Services\Mail\ProviderTransport(app(\App\Services\Mail\EmailSender::class)));
        \App\Support\Plans::boot();
        \App\Support\PlatformSettings::boot();

        $this->registerUsageMeters();

        View::composer('layouts.site', function ($view) {
            $view->with('navGroups', Content::featureGroups())->with('navSolutions', Content::solutions());
        });
    }

    /**
     * Plan meters counted from live experiences (section 47).
     */
    private function registerUsageMeters(): void
    {
        $usage = $this->app->make(Usage::class);
        // Live = published and included in the store's plan (others don't show, so they don't count).
        $live = fn (Store $store) => Experience::with('publishedVersion')->where('store_id', $store->id)->where('status', 'published')->get()
            ->filter(fn (Experience $e) => $store->allowsExperience($e->type, $e->publishedVersion?->config));

        // Types with their own limit (bundles, gifts, shipping bars, cart upsells, pre-orders) don't count here.
        $usage->register('active_experiences', fn (Store $store) => $live($store)->filter(fn (Experience $e) => Usage::countsAsActive($e->type))->count());
        // Several types can share a meter (bundles and BOGO; both gift types): count them together.
        foreach (collect(Registry::meters())->groupBy(fn ($meter) => $meter, true) as $meter => $types) {
            $usage->register($meter, fn (Store $store) => $live($store)->whereIn('type', $types->keys()->all())->count());
        }
        $usage->register('workflows', fn (Store $store) => \App\Models\Automation\Workflow::where('store_id', $store->id)->where('status', 'enabled')->count());
    }
}
