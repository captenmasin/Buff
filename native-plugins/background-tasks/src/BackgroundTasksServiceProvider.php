<?php

namespace Buff\BackgroundTasks;

use Buff\BackgroundTasks\Commands\RunScheduledTask;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Throwable;

class BackgroundTasksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([RunScheduledTask::class]);

        if (
            ! config('nativephp-internal.running') ||
            config('nativephp-internal.platform') !== 'android' ||
            getenv('BUFF_BACKGROUND_TASK_RUNNING') === '1'
        ) {
            return;
        }

        $this->app->booted(function (): void {
            try {
                $this->registerNativeTasks();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    private function registerNativeTasks(): void
    {
        if (! function_exists('nativephp_call')) {
            throw new RuntimeException('The NativePHP bridge is unavailable.');
        }

        if (function_exists('nativephp_can') && ! nativephp_can('BackgroundTasks.Register')) {
            throw new RuntimeException('The background task bridge is not registered.');
        }

        $result = nativephp_call('BackgroundTasks.Register', json_encode([
            'tasks' => $this->app->make(ScheduledTaskRegistry::class)->registrations(),
        ], JSON_THROW_ON_ERROR));

        if (! $result) {
            throw new RuntimeException('The background task bridge is not registered.');
        }

        $response = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($response)) {
            throw new RuntimeException('Background task registration returned an invalid response.');
        }

        if (($response['status'] ?? null) === 'error') {
            throw new RuntimeException($response['message'] ?? 'Background task registration failed.');
        }
    }
}
