<?php

namespace Buff\NativeRefresh;

use Buff\NativeRefresh\Commands\InstallNativeShellIntegrationsCommand;
use Illuminate\Support\ServiceProvider;

class NativeRefreshServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallNativeShellIntegrationsCommand::class,
            ]);
        }
    }
}
