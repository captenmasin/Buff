<?php

namespace Buff\CameraPermissions;

use Buff\CameraPermissions\Commands\InstallWebViewCameraAccessCommand;
use Illuminate\Support\ServiceProvider;

class CameraPermissionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallWebViewCameraAccessCommand::class,
            ]);
        }
    }
}
