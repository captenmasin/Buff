<?php

namespace App\Providers;

use App\Observers\SyncableObserver;
use App\Services\BuffCredentialStore;
use Illuminate\Support\Facades\Vite;
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
        if (config('inertia.devtools.enabled') === null) {
            config(['inertia.devtools.enabled' => Vite::isRunningHot()]);
        }

        foreach (array_keys(config('buff.sync_models')) as $model) {
            $model::observe(SyncableObserver::class);
        }
    }
}
