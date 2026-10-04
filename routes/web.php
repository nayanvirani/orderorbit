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

// Storefront analytics from the OrderOrbit Space web pixel.
Route::post('/api/pixel', [\App\Http\Controllers\PixelController::class, 'collect'])->middleware('throttle:240,1')->name('pixel.collect');
Route::options('/api/pixel', fn () => response('', 204)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'POST')->header('Access-Control-Allow-Headers', 'Content-Type'));
// Post-purchase funnel, called by the orderorbit-post-purchase extension (token signed by Shopify).
Route::middleware('throttle:300,1')->group(function () {
    Route::post('/api/post-purchase/offer', [\App\Http\Controllers\PostPurchaseController::class, 'offer'])->name('post-purchase.offer');
    Route::post('/api/post-purchase/sign', [\App\Http\Controllers\PostPurchaseController::class, 'sign'])->name('post-purchase.sign');
    Route::post('/api/post-purchase/decline', [\App\Http\Controllers\PostPurchaseController::class, 'decline'])->name('post-purchase.decline');
    Route::options('/api/post-purchase/{any}', fn () => response('', 204)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'POST')->header('Access-Control-Allow-Headers', 'Content-Type'))->where('any', 'offer|sign|decline');
});
Route::get('/api/sales-pop', [\App\Http\Controllers\SalesPopController::class, 'feed'])->middleware('throttle:600,1')->name('sales-pop.feed');

// Owner access to the public website while it's "coming soon".
Route::post('/site-access', [SiteController::class, 'unlock'])->middleware('throttle:5,1')->name('site.unlock');
Route::get('/site-access/lock', [SiteController::class, 'lock'])->name('site.lock');

// Public website (Part A)
Route::controller(SiteController::class)->name('site.')->middleware(\App\Http\Middleware\SitePreviewGate::class)->group(function () {
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
    Route::get('/docs', 'docsIndex')->name('docs.index');
    Route::get('/docs/{slug}', 'docs')->whereIn('slug', \App\Http\Controllers\SiteController::DOCS)->name('docs');
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
        Route::prefix('cro/bundles')->name('bundles.')->group(function () {
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
        Route::prefix('cro/progressive-gifts')->name('gifts.')->group(function () {
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
        Route::get('/cro/features/{feature}', [FeatureController::class, 'show'])->whereIn('feature', array_keys(Registry::features()))->name('features.show');

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
                    Route::post('/{experience}/import-orders', [ExperienceController::class, 'importOrders'])->whereNumber('experience')->name('import-orders');
                    Route::post('/{experience}/versions/{version}/restore', [ExperienceController::class, 'restoreVersion'])->whereNumber(['experience', 'version'])->name('versions.restore');
                    Route::post('/{experience}/{action}', [ExperienceController::class, 'lifecycle'])->whereNumber('experience')->whereIn('action', ['pause', 'resume', 'archive', 'unarchive', 'discard'])->name('lifecycle');
                });
                Route::get('/{experience}', [ExperienceController::class, 'show'])->whereNumber('experience')->name('show');
            });

            Route::get('/templates', [TemplateLibraryController::class, 'index'])->name('templates');
        });

        // Images for blocks, saved to the store's Shopify Files.
        Route::post('/uploads/image', [\App\Http\Controllers\App\UploadController::class, 'image'])->middleware('store.can:manage_experiences')->name('uploads.image');

        // Support tickets (section 34)
        Route::prefix('support')->name('support.')->controller(\App\Http\Controllers\App\SupportController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('throttle:10,60')->name('store');
            Route::get('/{ticket}', 'show')->whereNumber('ticket')->name('show');
            Route::post('/{ticket}/reply', 'reply')->whereNumber('ticket')->middleware('throttle:30,60')->name('reply');
            Route::post('/{ticket}/close', 'close')->whereNumber('ticket')->name('close');
            Route::get('/attachments/{attachment}', 'attachment')->whereNumber('attachment')->name('attachment');
        });

        // Audiences & Personalization (Phase 10)
        Route::prefix('audiences')->name('audiences.')->controller(\App\Http\Controllers\App\AudienceController::class)->group(function () {
            Route::get('/', fn () => redirect()->to(app_route('app.audiences.segments')))->name('index');
            Route::get('/segments', 'segments')->middleware('store.can:view_dashboard')->name('segments');
            Route::get('/rules', 'rules')->middleware('store.can:view_dashboard')->name('rules');
            Route::middleware('store.can:manage_experiences')->group(function () {
                Route::post('/segments', 'createSegment')->name('segments.store');
                Route::get('/segments/{segment}', 'editSegment')->whereNumber('segment')->name('segments.edit');
                Route::post('/segments/{segment}', 'updateSegment')->whereNumber('segment')->name('segments.update');
                Route::post('/segments/{segment}/duplicate', 'duplicateSegment')->whereNumber('segment')->name('segments.duplicate');
                Route::post('/segments/{segment}/archive', 'archiveSegment')->whereNumber('segment')->name('segments.archive');
                Route::post('/segments/{segment}/count', 'countSegment')->whereNumber('segment')->name('segments.count');
                Route::get('/rules/new', 'createRule')->name('rules.create');
                Route::post('/rules', 'saveRule')->name('rules.store');
                Route::get('/rules/{rule}', 'editRule')->whereNumber('rule')->name('rules.edit');
                Route::post('/rules/{rule}', 'saveRule')->whereNumber('rule')->name('rules.update');
                Route::post('/rules/{rule}/{action}', 'ruleAction')->whereNumber('rule')->whereIn('action', ['toggle', 'up', 'down', 'delete'])->name('rules.action');
            });
        });

        // A/B testing (Phase 9)
        Route::prefix('experiments')->name('experiments.')->controller(\App\Http\Controllers\App\ExperimentController::class)->group(function () {
            Route::get('/', 'index')->middleware('store.can:view_dashboard')->name('index');
            Route::get('/{experiment}', 'show')->whereNumber('experiment')->middleware('store.can:view_dashboard')->name('show');
            Route::get('/{experiment}/export', 'export')->whereNumber('experiment')->middleware('store.can:view_dashboard')->name('export');
            Route::middleware('store.can:manage_experiences')->group(function () {
                Route::post('/', 'store')->name('store');
                Route::get('/{experiment}/edit', 'edit')->whereNumber('experiment')->name('edit');
                Route::post('/{experiment}', 'update')->whereNumber('experiment')->name('update');
                Route::post('/{experiment}/duplicate', 'duplicate')->whereNumber('experiment')->name('duplicate');
                Route::post('/{experiment}/delete', 'destroy')->whereNumber('experiment')->name('destroy');
                Route::post('/{experiment}/{action}', 'action')->whereNumber('experiment')->whereIn('action', ['pause', 'resume', 'stop', 'apply'])->name('action');
            });
        });

        // Automation (Phase 7)
        Route::prefix('automation')->name('automation.')->controller(\App\Http\Controllers\App\AutomationController::class)->group(function () {
            Route::get('/', 'index')->middleware('store.can:view_dashboard')->name('index');
            Route::get('/templates', 'templates')->middleware('store.can:view_dashboard')->name('templates');
            Route::get('/runs', 'runs')->middleware('store.can:view_dashboard')->name('runs');
            Route::get('/runs/{run}', 'run')->whereNumber('run')->middleware('store.can:view_dashboard')->name('runs.show');
            Route::get('/inbox', 'inbox')->middleware('store.can:view_dashboard')->name('inbox');
            Route::get('/emails', 'emails')->middleware('store.can:view_dashboard')->name('emails');
            Route::middleware('store.can:manage_experiences')->group(function () {
                Route::post('/workflows', 'store')->name('store');
                Route::get('/workflows/{workflow}', 'edit')->whereNumber('workflow')->name('edit');
                Route::post('/workflows/{workflow}', 'update')->whereNumber('workflow')->name('update');
                Route::post('/workflows/{workflow}/toggle', 'toggle')->whereNumber('workflow')->name('toggle');
                Route::post('/workflows/{workflow}/delete', 'destroy')->whereNumber('workflow')->name('destroy');
                Route::post('/workflows/{workflow}/versions/{version}/restore', 'restore')->whereNumber(['workflow', 'version'])->name('restore');
                Route::post('/inbox/{item}/done', 'done')->whereNumber('item')->name('inbox.done');
                Route::post('/runs/{run}/retry', 'retry')->whereNumber('run')->name('runs.retry');
            });
        });

        Route::get('/analytics', [\App\Http\Controllers\App\AnalyticsController::class, 'index'])->middleware('store.can:view_dashboard')->name('analytics');
        Route::post('/analytics/connect', [\App\Http\Controllers\App\AnalyticsController::class, 'connect'])->middleware('store.can:manage_settings')->name('analytics.connect');
        // Phase 8: Event Explorer, Funnels, Revenue & Attribution, Customer Journey.
        Route::prefix('analytics')->name('analytics.')->middleware('store.can:view_dashboard')->controller(\App\Http\Controllers\App\AnalyticsReportsController::class)->group(function () {
            Route::get('/events', 'events')->name('events');
            Route::get('/funnels', 'funnels')->name('funnels');
            Route::get('/funnels/{funnel}', 'funnel')->whereNumber('funnel')->name('funnel');
            Route::get('/revenue', 'revenue')->name('revenue');
            Route::get('/journeys', 'journeys')->name('journeys');
            Route::get('/journeys/{visitor}', 'journey')->where('visitor', '[A-Za-z0-9_\-]{1,64}')->name('journey');
            Route::middleware('store.can:manage_experiences')->group(function () {
                Route::post('/funnels', 'storeFunnel')->name('funnels.store');
                Route::post('/funnels/{funnel}', 'updateFunnel')->whereNumber('funnel')->name('funnels.update');
                Route::post('/funnels/{funnel}/delete', 'destroyFunnel')->whereNumber('funnel')->name('funnels.destroy');
            });
        });

        // Older links
        Route::get('/templates', fn () => redirect()->to(app_route('app.cro.templates')))->name('templates');
        Route::get('/bundles', fn () => redirect()->to(app_route('app.bundles.index')));
        Route::get('/progressive-gifts', fn () => redirect()->to(app_route('app.gifts.index')));

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
            Route::get('/integrations', [\App\Http\Controllers\App\IntegrationsController::class, 'integrations'])->name('integrations');
            Route::get('/privacy', [\App\Http\Controllers\App\IntegrationsController::class, 'privacy'])->name('privacy');
            Route::middleware('store.can:manage_settings')->group(function () {
                Route::post('/privacy', [\App\Http\Controllers\App\IntegrationsController::class, 'updatePrivacy'])->name('privacy.update');
                Route::get('/privacy/export', [\App\Http\Controllers\App\IntegrationsController::class, 'export'])->name('privacy.export');
                Route::post('/privacy/delete-analytics', [\App\Http\Controllers\App\IntegrationsController::class, 'destroyAnalytics'])->name('privacy.delete');
            });
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

// Internal Admin (section 33): the OrderOrbit team's console, separate from the merchant app.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Admin\AuthController::class, 'show'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('logout');

    Route::middleware(\App\Http\Middleware\EnsureAdmin::class)->controller(\App\Http\Controllers\Admin\AdminController::class)->group(function () {
        Route::get('/', 'home')->name('home');
        Route::get('/stores', 'stores')->name('stores');
        Route::get('/stores/{store}', 'store')->whereNumber('store')->name('store');
        Route::get('/failures', 'failures')->name('failures');
        Route::get('/analytics', 'analytics')->name('analytics');
        Route::get('/templates', 'templates')->name('templates');
        Route::post('/templates/{template}/toggle', 'toggleTemplate')->whereNumber('template')->name('templates.toggle');
        Route::get('/flags', 'flags')->name('flags');
        Route::post('/flags', 'saveFlag')->name('flags.save');
        Route::post('/flags/{flag}/delete', 'deleteFlag')->whereNumber('flag')->name('flags.delete');
        Route::get('/tickets', 'tickets')->name('tickets');
        Route::get('/tickets/{ticket}', 'ticket')->whereNumber('ticket')->name('ticket');
        Route::post('/tickets/{ticket}/reply', 'replyTicket')->whereNumber('ticket')->name('ticket.reply');
        Route::post('/tickets/{ticket}', 'updateTicket')->whereNumber('ticket')->name('ticket.update');
        Route::get('/attachments/{attachment}', 'attachment')->whereNumber('attachment')->name('attachment');
        Route::get('/audit', 'audit')->name('audit');
    });
});

