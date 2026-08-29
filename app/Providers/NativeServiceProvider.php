<?php

namespace App\Providers;

use Buff\AppleHealth\AppleHealthServiceProvider;
use Buff\BackgroundTasks\BackgroundTasksServiceProvider;
use Buff\CameraPermissions\CameraPermissionsServiceProvider;
use Buff\HealthConnect\HealthConnectServiceProvider;
use Buff\InAppPurchases\InAppPurchasesServiceProvider;
use Buff\NativeRefresh\NativeRefreshServiceProvider;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Providers\BrowserServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }

    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            AppleHealthServiceProvider::class,
            CameraPermissionsServiceProvider::class,
            HealthConnectServiceProvider::class,
            NativeRefreshServiceProvider::class,
            BackgroundTasksServiceProvider::class,
            BrowserServiceProvider::class,
            InAppPurchasesServiceProvider::class,

        ];
    }
}
