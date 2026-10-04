<?php

use Illuminate\Support\Facades\Schedule;

/*
| Run by the worker service on Railway: php artisan schedule:work
*/

// Workflows: resume runs whose wait or retry delay is over.
Schedule::command('orderorbit:automation-tick')->everyMinute()->withoutOverlapping(10);

// Analytics, order totals and Sales pop purchases past their retention window.
Schedule::command('orderorbit:prune-analytics')->dailyAt('03:15');
