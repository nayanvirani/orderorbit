<?php

use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\OnboardingController;
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
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'update'])->name('onboarding.update');
    Route::get('/billing', [BillingController::class, 'index'])->name('billing');
    Route::post('/billing', [BillingController::class, 'subscribe'])->name('billing.subscribe');
});

// Shopify webhooks (app lifecycle, billing, GDPR compliance)
Route::post('/webhooks/shopify', WebhookController::class)
    ->middleware('shopify.webhook')
    ->name('webhooks.shopify');
