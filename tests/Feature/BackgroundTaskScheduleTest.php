<?php

use App\Services\HealthConnectBridge;
use Buff\BackgroundTasks\ScheduledTaskRegistry;

it('registers the ten minute health connect command for Android', function (): void {
    $registry = app(ScheduledTaskRegistry::class);
    $task = collect($registry->tasks())
        ->sole('command', 'health-connect:sync');

    expect($task)
        ->toMatchArray([
            'expression' => '*/10 * * * *',
            'interval_minutes' => 10,
        ])
        ->and($task['id'])->toMatch('/\A[a-f0-9]{64}\z/')
        ->and($registry->registrations())->toBe([[
            'id' => $task['id'],
            'interval_minutes' => 10,
        ]]);
});

it('runs a scheduled command by its validated task ID', function (): void {
    $calls = [];
    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        function (string $method, string $payload) use (&$calls): string {
            $calls[] = [$method, $payload];

            return json_encode(['status' => 'sync_queued'], JSON_THROW_ON_ERROR);
        },
    ));

    $task = collect(app(ScheduledTaskRegistry::class)->tasks())
        ->sole('command', 'health-connect:sync');

    $this->artisan('background-task:run', ['task' => $task['id']])
        ->expectsOutputToContain("BUFF_BACKGROUND_TASK_OK:{$task['id']}")
        ->assertSuccessful();

    expect($calls)->toBe([['HealthConnect.SyncNow', '[]']]);
});

it('fails when a scheduled command cannot queue its work', function (): void {
    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        fn (): string => json_encode([
            'status' => 'error',
            'message' => 'Native sync failed.',
        ], JSON_THROW_ON_ERROR),
    ));

    $task = collect(app(ScheduledTaskRegistry::class)->tasks())
        ->sole('command', 'health-connect:sync');

    $this->artisan('background-task:run', ['task' => $task['id']])
        ->expectsOutputToContain('Native sync failed.')
        ->assertFailed();
});

it('rejects an invalid background task ID', function (): void {
    expect(fn () => app(ScheduledTaskRegistry::class)->run('health-connect:sync'))
        ->toThrow(RuntimeException::class, 'Invalid background task ID.');
});
