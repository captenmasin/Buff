<?php

namespace App\Providers;

use Buff\HealthConnect\HealthConnectServiceProvider;
use Illuminate\Support\ServiceProvider;

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
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            HealthConnectServiceProvider::class,
        ];
    }
}
