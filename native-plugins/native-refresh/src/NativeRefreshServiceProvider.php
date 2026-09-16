<?php

namespace Buff\NativeRefresh;

use Buff\NativeRefresh\Commands\InstallNativeShellIntegrationsCommand;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class NativeRefreshServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallNativeShellIntegrationsCommand::class,
            ]);

            Event::listen(CommandStarting::class, function (CommandStarting $event): void {
                $platform = $this->nativeBuildPlatform($event);

                if ($platform === null) {
                    return;
                }

                $marker = public_path('build/native-platform');
                $builtFor = is_file($marker) ? trim((string) file_get_contents($marker)) : 'unknown';

                if ($builtFor !== $platform) {
                    throw new RuntimeException(
                        "Frontend assets were built for {$builtFor}; run `pnpm run build:{$platform}` before this native {$platform} command.",
                    );
                }
            });
        }
    }

    private function nativeBuildPlatform(CommandStarting $event): ?string
    {
        if ($event->command === 'native:build') {
            return $event->input->getOption('cleanup-provisioning-profile') ? null : 'ios';
        }

        if (! in_array($event->command, ['native:run', 'native:package', 'native:watch'], true)) {
            return null;
        }

        if ($event->command === 'native:package') {
            if ($event->input->getOption('test-push') || $event->input->getOption('test-upload') || $event->input->getOption('validate-profile')) {
                return null;
            }

            $platform = match (true) {
                $event->input->getOption('ios') => 'ios',
                $event->input->getOption('android') => 'android',
                default => $event->input->getArgument('platform'),
            };

            if ($event->input->getOption('skip-prepare') && in_array($platform, ['android', 'a'], true)) {
                return null;
            }
        } elseif ($event->command === 'native:watch') {
            $platform = match (true) {
                $event->input->getOption('ios') => 'ios',
                $event->input->getOption('android') => 'android',
                default => $event->input->getArgument('platform'),
            };
        } else {
            $platform = $event->input->getArgument('os');
        }

        return match (strtolower((string) $platform)) {
            'android', 'a' => 'android',
            'ios', 'i' => 'ios',
            default => throw new RuntimeException('Specify ios or android so Buff can verify the frontend asset build.'),
        };
    }
}
