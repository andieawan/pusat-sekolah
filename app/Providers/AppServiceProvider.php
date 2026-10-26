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
        // Proxy tepercaya dari config (lihat config/app.php: trusted_proxies).
        if ($proxi = trim((string) config('app.trusted_proxies'))) {
            \Illuminate\Http\Middleware\TrustProxies::at($proxi === '*' ? '*' : array_map('trim', explode(',', $proxi)));
        }
        // Endpoint token/userinfo SSO: dibatasi per IP.
        RateLimiter::for('sso-token', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));

        // API data induk: dibatasi per aplikasi (client_id), cadangan per IP.
        RateLimiter::for('api-master', fn (Request $r) => Limit::perMinute(120)->by($r->getUser() ?: $r->ip()));
    }
}
