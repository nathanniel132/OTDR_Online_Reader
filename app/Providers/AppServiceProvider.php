<?php

namespace App\Providers;

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
        // Pastikan folder compiled views dibuat jika di environment serverless /tmp
        $viewPath = config('view.compiled');
        if ($viewPath && ! is_dir($viewPath)) {
            @mkdir($viewPath, 0755, true);
        }
    }
}
