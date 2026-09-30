<?php

use App\Experiences\Registry;
use App\Http\Controllers\App\ActivityController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\BrandingController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\BundleController;
use App\Http\Controllers\App\ExperienceController;
use App\Http\Controllers\App\FeatureController;
use App\Http\Controllers\App\GiftController;
use App\Http\Controllers\App\OnboardingController;
use App\Http\Controllers\App\StoreSettingsController;
use App\Http\Controllers\App\TemplateLibraryController;
use App\Http\Controllers\App\UsersController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Public website (Part A)
Route::controller(SiteController::class)->name('site.')->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/how-it-works', 'how')->name('how');
    Route::get('/features', 'features')->name('features');
    Route::get('/features/{slug}', 'feature')->name('feature');
    Route::get('/solutions', 'solutions')->name('solutions');
    Route::get('/solutions/{slug}', 'solution')->name('solution');
    Route::get('/templates', 'templates')->name('templates');
    Route::get('/pricing', 'pricing')->name('pricing');
    Route::get('/resources', 'resources')->name('resources');
    Route::get('/blog', 'blog')->name('blog');
    Route::get('/help', 'help')->name('help');
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact', 'submitContact')->middleware('throttle:5,1')->name('contact.submit');
    Route::get('/about', 'about')->name('about');
    Route::get('/security', 'security')->name('security');
    Route::get('/privacy', 'privacy')->name('privacy');
    Route::get('/terms', 'terms')->name('terms');
    Route::get('/dpa', 'dpa')->name('dpa');
    Route::get('/sitemap.xml', 'sitemap')->name('sitemap');
});

// Merchant embedded app (Part B)
Route::prefix('app')->middleware('shopify.auth')->name('app.')->group(function () {
    // Billing stays reachable without a plan: it's where plans are chosen.
    Route::get('/settings/billing', [BillingController::class, 'index'])->name('settings.billing');

    Route::middleware('store.plan')->group(function () {
        Route::get('/', DashboardController::class)->middleware('store.can:view_dashboard')->name('dashboard');
        Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
        Route::post('/onboarding', [OnboardingController::class, 'update'])->middleware('store.can:manage_settings')->name('onboarding.update');

        // Bundles module (MoonBundle-style): list, type, model, editor
        Route::prefix('bundles')->name('bundles.')->group(function () {
            Route::get('/', [BundleController::class, 'index'])->name('index');
            Route::middleware('store.can:manage_experiences')->group(function () {
                Route::get('/new', [BundleController::class, 'types'])->name('types');
                Route::get('/new/{type}', [BundleController::class, 'models'])->name('models');
                Route::post('/', [BundleController::class, 'store'])->name('store');
                Route::get('/{bundle}', [BundleController::class, 'edit'])->whereNumber('bundle')->name('edit');
                Route::post('/{bundle}', [BundleController::class, 'update'])->whereNumber('bundle')->name('update');
                Route::post('/{bundle}/toggle', [BundleController::class, 'toggle'])->whereNumber('bundle')->name('toggle');
            });
        });

        // Progressive gifts module: list, template, editor
        Route::prefix('progressive-gifts')->name('gifts.')->group(function () {
            Route::get('/', [GiftController::class, 'index'])->name('index');
            Route::middleware('store.can:manage_experiences')->group(function () {
                Route::get('/new', [GiftController::class, 'models'])->name('models');
                Route::post('/', [GiftController::class, 'store'])->name('store');
                Route::get('/{gift}', [GiftController::class, 'edit'])->whereNumber('gift')->name('edit');
                Route::post('/{gift}', [GiftController::class, 'update'])->whereNumber('gift')->name('update');
                Route::post('/{gift}/toggle', [GiftController::class, 'toggle'])->whereNumber('gift')->name('toggle');
            });
        });

        // Feature pages (app navigation): Cart upsells, Countdown timer, Sticky add to cart, Trust badges
        Route::get('/features/{feature}', [FeatureController::class, 'show'])->whereIn('feature', array_keys(Registry::features()))->name('features.show');

        // CRO experiences (section 15, D2)
        Route::prefix('cro')->name('cro.')->group(function () {
            Route::get('/', [ExperienceController::class, 'overview'])->middleware('store.can:view_dashboard')->name('overview');

            Route::prefix('experiences')->name('experiences.')->group(function () {
                Route::get('/', [ExperienceController::class, 'index'])->name('index');
                Route::get('/export', [ExperienceController::class, 'export'])->name('export');
                Route::middleware('store.can:manage_experiences')->group(function () {
                    Route::get('/new', [ExperienceController::class, 'create'])->name('create');
                    Route::post('/', [ExperienceController::class, 'store'])->name('store');
                    Route::post('/bulk', [ExperienceController::class, 'bulk'])->name('bulk');
                    Route::get('/{experience}/edit', [ExperienceController::class, 'edit'])->whereNumber('experience')->name('edit');
                    Route::post('/{experience}', [ExperienceController::class, 'update'])->whereNumber('experience')->name('update');
                    Route::post('/{experience}/publish', [ExperienceController::class, 'publish'])->whereNumber('experience')->name('publish');
                    Route::post('/{experience}/duplicate', [ExperienceController::class, 'duplicate'])->whereNumber('experience')->name('duplicate');
                    Route::post('/{experience}/placement', [ExperienceController::class, 'checkPlacement'])->whereNumber('experience')->name('placement');
                    Route::post('/{experience}/versions/{version}/restore', [ExperienceController::class, 'restoreVersion'])->whereNumber(['experience', 'version'])->name('versions.restore');
                    Route::post('/{experience}/{action}', [ExperienceController::class, 'lifecycle'])->whereNumber('experience')->whereIn('action', ['pause', 'resume', 'archive', 'unarchive', 'discard'])->name('lifecycle');
                });
                Route::get('/{experience}', [ExperienceController::class, 'show'])->whereNumber('experience')->name('show');
            });

            Route::get('/{type}', [ExperienceController::class, 'index'])->whereIn('type', array_keys(Registry::types()))->name('type');
        });

        Route::get('/templates', [TemplateLibraryController::class, 'index'])->name('templates');

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', fn () => redirect()->to(app_route('app.settings.store')))->name('index');
            Route::get('/store', [StoreSettingsController::class, 'show'])->name('store');
            Route::post('/store/recheck', [StoreSettingsController::class, 'recheck'])->middleware('store.can:manage_settings')->name('store.recheck');
            Route::post('/store/reconnect', [StoreSettingsController::class, 'reconnect'])->middleware('store.can:manage_settings')->name('store.reconnect');

            Route::middleware('store.can:manage_users')->group(function () {
                Route::get('/users', [UsersController::class, 'index'])->name('users');
                Route::post('/users', [UsersController::class, 'invite'])->name('users.invite');
                Route::post('/users/{user}/role', [UsersController::class, 'updateRole'])->name('users.role');
                Route::post('/users/{user}/remove', [UsersController::class, 'remove'])->name('users.remove');
                Route::post('/users/{user}/restore', [UsersController::class, 'restore'])->name('users.restore');
            });

            Route::get('/branding', [BrandingController::class, 'show'])->name('branding');
            Route::post('/branding', [BrandingController::class, 'update'])->middleware('store.can:manage_settings')->name('branding.update');

            Route::get('/activity', [ActivityController::class, 'index'])->middleware('store.can:view_activity')->name('activity');
        });

        // Older links before the move to Settings.
        Route::get('/billing', fn () => redirect()->to(app_route('app.settings.billing')))->name('billing');
    });
});

// The storefront runtime, served from the theme extension so the builder preview
// renders with exactly the code shoppers get.
Route::get('/storefront/{file}', function (string $file) {
    $path = base_path('extensions/orderorbit-theme/assets/'.$file);
    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => str_ends_with($file, '.css') ? 'text/css' : 'application/javascript',
        'Cache-Control' => 'public, max-age=300',
    ]);
})->where('file', 'orderorbit\.(js|css)|oo-[a-z\-]+\.js')->name('storefront.asset');

// Shopify webhooks (app lifecycle, billing, GDPR compliance)
Route::post('/webhooks/shopify', WebhookController::class)
    ->middleware('shopify.webhook')
    ->name('webhooks.shopify');
