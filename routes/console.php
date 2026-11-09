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

// Ops alerts for the Growvia team (G26): checks every 5 minutes, a summary every morning.
Schedule::command('orderorbit:ops-check')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('orderorbit:ops-check --digest')->dailyAt('03:30'); // 09:00 in India
// Announcements (G41): email store owners when an announcement marked "email owners" starts.
Schedule::command('orderorbit:weekly-summary')->weeklyOn(1, '03:30')->withoutOverlapping(60); // G16, Monday 09:00 India
Schedule::command('orderorbit:protect-check')->hourlyAt(20)->withoutOverlapping(55); // Protect (phase 4)
Schedule::command('orderorbit:profit-goals')->hourlyAt(50)->withoutOverlapping(55); // Profit goals (G6): once a month per store when behind
Schedule::command('orderorbit:monthly-reports')->hourlyAt(40)->withoutOverlapping(55); // Monthly Business Report (G15): due on the 1st in each store's time zone, sent once
Schedule::command('orderorbit:url-monitor')->everyFiveMinutes()->withoutOverlapping(10); // URL monitor (G10)
Schedule::command('orderorbit:profit-sync')->everyFiveMinutes()->withoutOverlapping(60); // Profit (phase 3)
Schedule::command('orderorbit:lifecycle-emails')->hourly()->withoutOverlapping(30); // G24
Schedule::command('orderorbit:announcement-emails')->everyFiveMinutes()->withoutOverlapping(10);

// Live chat (G43): email the team about chats nobody picked up, close answered chats that went quiet.
Schedule::command('orderorbit:chat-sweep')->everyMinute()->withoutOverlapping(5);
