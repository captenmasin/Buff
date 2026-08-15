<?php

namespace Buff\BackgroundTasks;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class ScheduledTaskRegistry
{
    private const ARTISAN_PREFIX = 'php artisan ';

    public function __construct(
        private readonly Kernel $kernel,
        private readonly Container $container,
    ) {}

    /**
     * @return list<array{id: string, command: string, expression: string, interval_minutes: int}>
     */
    public function tasks(): array
    {
        $this->kernel->bootstrap();
        $tasks = [];

        foreach ($this->container->make(Schedule::class)->events() as $event) {
            $intervalMinutes = $this->intervalMinutes($event->expression);
            $normalizedCommand = Event::normalizeCommand($event->command ?? '');

            if ($intervalMinutes === null || ! str_starts_with($normalizedCommand, self::ARTISAN_PREFIX)) {
                continue;
            }

            $command = substr($normalizedCommand, strlen(self::ARTISAN_PREFIX));
            $tasks[] = [
                'id' => hash('sha256', $command."\0".$event->expression),
                'command' => $command,
                'expression' => $event->expression,
                'interval_minutes' => $intervalMinutes,
            ];
        }

        return $tasks;
    }

    /**
     * @return list<array{id: string, interval_minutes: int}>
     */
    public function registrations(): array
    {
        return array_map(
            fn (array $task): array => [
                'id' => $task['id'],
                'interval_minutes' => $task['interval_minutes'],
            ],
            $this->tasks(),
        );
    }

    public function run(string $taskId): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $taskId) !== 1) {
            throw new RuntimeException('Invalid background task ID.');
        }

        foreach ($this->tasks() as $task) {
            if ($task['id'] !== $taskId) {
                continue;
            }

            $exitCode = Artisan::call($task['command']);
            $output = Artisan::output();

            if ($exitCode !== 0) {
                throw new RuntimeException(trim($output) ?: "Background task exited with code {$exitCode}.");
            }

            return $output;
        }

        throw new RuntimeException('Background task is no longer scheduled.');
    }

    private function intervalMinutes(string $expression): ?int
    {
        return match ($expression) {
            '*/10 * * * *' => 10,
            '0 * * * *' => 60,
            default => null,
        };
    }
}
