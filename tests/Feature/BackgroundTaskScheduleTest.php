<?php

use App\Services\HealthConnectBridge;
use Buff\BackgroundTasks\ScheduledTaskRegistry;

it('registers the ten minute health connect command for Android', function (): void {
    $registry = app(ScheduledTaskRegistry::class);
    $tasks = collect($registry->tasks());
    $task = $tasks->sole('command', 'health-connect:sync');

    expect($task)
        ->toMatchArray([
            'expression' => '*/10 * * * *',
            'interval_minutes' => 10,
        ])
        ->and($task['id'])->toMatch('/\A[a-f0-9]{64}\z/')
        ->and($registry->registrations())->toHaveCount(2)
        ->and($registry->registrations())->toContain([
            'id' => $task['id'],
            'interval_minutes' => 10,
        ]);
});

it('runs a scheduled command by its validated task ID', function (bool $writeResult): void {
    $calls = [];
    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        function (string $method, string $payload) use (&$calls): string {
            $calls[] = [$method, $payload];

            return json_encode(['status' => 'sync_queued'], JSON_THROW_ON_ERROR);
        },
    ));

    $task = collect(app(ScheduledTaskRegistry::class)->tasks())
        ->sole('command', 'health-connect:sync');
    $resultPath = storage_path('framework/testing/background-task-'.uniqid().'.result');

    try {
        $this->artisan('background-task:run', [
            '--task' => $task['id'],
            '--result' => $writeResult ? $resultPath : null,
        ])
            ->expectsOutputToContain("BUFF_BACKGROUND_TASK_OK:{$task['id']}")
            ->assertSuccessful();

        expect($calls)->toBe([['HealthConnect.SyncNow', '[]']]);

        if ($writeResult) {
            expect(file_get_contents($resultPath))->toBe("BUFF_BACKGROUND_TASK_OK:{$task['id']}");
        } else {
            expect(is_file($resultPath))->toBeFalse();
        }
    } finally {
        if (is_file($resultPath)) {
            unlink($resultPath);
        }
    }
})->with([false, true]);

it('fails when a scheduled command cannot queue its work', function (): void {
    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        fn (): string => json_encode([
            'status' => 'error',
            'message' => 'Native sync failed.',
        ], JSON_THROW_ON_ERROR),
    ));

    $task = collect(app(ScheduledTaskRegistry::class)->tasks())
        ->sole('command', 'health-connect:sync');
    $resultPath = storage_path('framework/testing/background-task-failed-'.uniqid().'.result');

    $this->artisan('background-task:run', ['--task' => $task['id'], '--result' => $resultPath])
        ->expectsOutputToContain('Native sync failed.')
        ->assertFailed();

    expect(is_file($resultPath))->toBeFalse();
});

it('registers the ten minute apple health command', function (): void {
    $registry = app(ScheduledTaskRegistry::class);
    $task = collect($registry->tasks())
        ->sole('command', 'apple-health:sync');

    expect($task)
        ->toMatchArray([
            'expression' => '*/10 * * * *',
            'interval_minutes' => 10,
        ]);
});

it('rejects an invalid background task ID', function (): void {
    expect(fn () => app(ScheduledTaskRegistry::class)->run('health-connect:sync'))
        ->toThrow(RuntimeException::class, 'Invalid background task ID.');
});
