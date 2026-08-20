<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Tighter than the general 'api' limiter: login is a credential-guessing
        // target, so 60/min (the general API limit) is far too loose to resist
        // brute-forcing a single account. Keyed by email+IP together so an
        // attacker can't dodge the limit by rotating IPs against one account,
        // and one IP guessing many accounts doesn't lock out unrelated users
        // sharing that IP.
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->string('email').'|'.$request->ip());
        });
    }
}
