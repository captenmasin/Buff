<?php

use App\Models\DailyLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('deletes all data except user accounts', function (): void {
    $user = User::factory()->create();

    DailyLog::query()->create([
        'date' => now()->toDateString(),
        'burned_calories' => 500,
    ]);

    DB::table('cache')->insert([
        'key' => 'test-key',
        'value' => 'test-value',
        'expiration' => now()->addHour()->getTimestamp(),
    ]);

    $this->artisan('app:clear-data', ['--force' => true])
        ->expectsOutput('All data except user accounts has been deleted.')
        ->assertSuccessful();

    $this->assertModelExists($user);
    $this->assertDatabaseEmpty('daily_logs');
    $this->assertDatabaseEmpty('cache');
});

it('does not delete data when confirmation is declined', function (): void {
    DailyLog::query()->create([
        'date' => now()->toDateString(),
        'burned_calories' => 500,
    ]);

    $this->artisan('app:clear-data')
        ->expectsConfirmation('Delete all data except user accounts?', 'no')
        ->expectsOutput('No data was deleted.')
        ->assertSuccessful();

    $this->assertDatabaseCount('daily_logs', 1);
});
