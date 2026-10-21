<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Paksa HTTPS di production / Vercel serverless untuk mencegah Mixed Content blocker
        if (config('app.env') === 'production' || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            URL::forceScheme('https');
        }

        // Pastikan folder compiled views dibuat jika di environment serverless /tmp
        $viewPath = config('view.compiled');
        if ($viewPath && ! is_dir($viewPath)) {
            @mkdir($viewPath, 0755, true);
        }
    }
}
