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
        // Application services are resolved lazily.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::policy(\App\Models\VaultFile::class,\App\Policies\VaultFilePolicy::class);
        foreach([3,5,6,10,20,240] as $limit) {
            \Illuminate\Support\Facades\RateLimiter::for('vault-'.$limit,fn(\Illuminate\Http\Request $request)=>\Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by($request->route()->getActionName().'|'.$request->ip().'|'.($request->user()?->id ?? 'guest')));
        }
    }
}
