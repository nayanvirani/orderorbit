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
        // Live offers (per type and in total) are counted by Usage itself; these count the rest.
        $usage->register('workflows', fn (Store $store) => \App\Models\Automation\Workflow::where('store_id', $store->id)->where('status', 'enabled')->count());
        $usage->register('running_tests', fn (Store $store) => \App\Models\Experiments\Experiment::where('store_id', $store->id)->where('status', 'running')->count());
        $usage->register('personalization_rules', fn (Store $store) => \App\Models\Audiences\PersonalizationRule::where('store_id', $store->id)->where('enabled', true)->count());
        $usage->register('segments', fn (Store $store) => \App\Models\Audiences\Segment::where('store_id', $store->id)->active()->count());
    }
}
