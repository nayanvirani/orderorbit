<?php

use Illuminate\Support\Facades\Schedule;

/*
| Run by the worker service on Railway: php artisan schedule:work
*/

// Workflows: resume runs whose wait or retry delay is over.
Schedule::call(fn () => app(\App\Services\Experiments\ExperimentManager::class)->completeDue())->name('experiments-complete-due')->hourly()->withoutOverlapping();
Schedule::command('orderorbit:automation-tick')->everyMinute()->withoutOverlapping(10);
// Plan features and limits reach storefronts and checkouts when they change (e.g. a complimentary plan ends).
Schedule::command('orderorbit:apply-entitlements')->everyFiveMinutes()->withoutOverlapping(15);
// Workflow emails: send through the super admin's email providers, with failover and retries.
Schedule::command('orderorbit:send-emails')->everyMinute()->withoutOverlapping(10);

// Analytics, order totals and Sales pop purchases past their retention window.
Schedule::command('orderorbit:prune-analytics')->dailyAt('03:15');
