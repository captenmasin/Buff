<?php

declare(strict_types=1);

namespace BlessedZulu\NativePhpAdmob;

use BlessedZulu\NativePhpAdmob\Commands\SubstituteManifestPlaceholdersCommand;
use BlessedZulu\NativePhpAdmob\Contracts\Bridge;
use BlessedZulu\NativePhpAdmob\Events\ConsentChanged;
use BlessedZulu\NativePhpAdmob\Http\Controllers\AdmobCallController;
use BlessedZulu\NativePhpAdmob\Support\NativeBridge;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdmobServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admob.php', 'admob');

        $this->app->singleton(Bridge::class, fn () => new NativeBridge);

        $this->app->singleton('admob', function ($app) {
            $config = (array) $app['config']->get('admob', []);

            return new Admob(
                $app->make(Bridge::class),
                $config,
                $app['cache']->store($config['frequency_store'] ?? null),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admob.php' => config_path('admob.php'),
        ], 'admob-config');

        Route::prefix('_admob')->post('call', AdmobCallController::class);

        Event::listen(ConsentChanged::class, function (ConsentChanged $event) {
            $this->app->make('admob')->onConsentChanged($event->status);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SubstituteManifestPlaceholdersCommand::class,
            ]);
        }
    }
}
