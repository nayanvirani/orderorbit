<?php

use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\OnboardingController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Public website (Part A)
Route::controller(SiteController::class)->group(function () {
    Route::get('/', 'home')->name('site.home');
    Route::get('/pricing', 'pricing')->name('site.pricing');
    Route::get('/privacy', 'privacy')->name('site.privacy');
    Route::get('/terms', 'terms')->name('site.terms');
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
