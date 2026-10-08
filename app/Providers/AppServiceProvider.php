<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Config, search, detail, status polls, beacons.
        RateLimiter::for('embed', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        // Creating a booking holds real seats. A bot hammering this would
        // make every departure look sold out (PLAN.md R3).
        RateLimiter::for('embed-booking', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}