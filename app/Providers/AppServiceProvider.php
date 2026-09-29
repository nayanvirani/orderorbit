<?php

namespace App\Providers;

use App\Services\Shopify\SessionToken;
use App\Support\Content;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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

        View::composer('layouts.site', function ($view) {
            $view->with('navGroups', Content::featureGroups())->with('navSolutions', Content::solutions());
        });
    }
}
