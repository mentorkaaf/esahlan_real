<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Force HTTPS for all asset() / url() calls on production.
        // Shared hosting often strips X-Forwarded-Proto, so asset() returns http:// even
        // though the site is served over HTTPS. Android blocks HTTP (cleartext) by default.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
