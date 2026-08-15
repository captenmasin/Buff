<?php

namespace App\Providers;

use App\Observers\SyncableObserver;
use App\Services\BuffCredentialStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BuffCredentialStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (array_keys(config('buff.sync_models')) as $model) {
            $model::observe(SyncableObserver::class);
        }
    }
}
