<?php

use App\Models\SyncOutbox;
use App\Models\WorkoutEntry;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('creates a workout entry', function (): void {
    $this->post('/workouts', [
        'date' => '2026-05-19',
        'title' => 'Strength training',
        'calories_burned' => 250,
        'time' => '07:45',
    ])->assertRedirect('/?date=2026-05-19');

    $workout = WorkoutEntry::query()->first();

    expect($workout->title)->toBe('Strength training')
        ->and($workout->calories_burned)->toBe(250)
        ->and($workout->source_type)->toBe(WorkoutEntry::SOURCE_MANUAL)
        ->and($workout->logged_at->format('Y-m-d H:i:s'))->toBe('2026-05-19 07:45:00');
});

it('updates a workout entry', function (): void {
    $workout = WorkoutEntry::query()->create([
        'date' => '2026-05-19',
        'title' => 'Strength training',
        'calories_burned' => 250,
        'logged_at' => '2026-05-19 07:45:00',
        'source_type' => WorkoutEntry::SOURCE_MANUAL,
    ]);

    $this->put("/workouts/{$workout->id}", [
        'date' => '2026-05-20',
        'title' => 'Evening run',
        'calories_burned' => 320,
        'time' => '18:30',
    ])->assertRedirect('/?date=2026-05-20');

    expect($workout->refresh())
        ->title->toBe('Evening run')
        ->calories_burned->toBe(320)
        ->source_type->toBe(WorkoutEntry::SOURCE_MANUAL)
        ->and($workout->date->toDateString())->toBe('2026-05-20')
        ->and($workout->logged_at->format('Y-m-d H:i:s'))->toBe('2026-05-20 18:30:00');
});

it('keeps edits to imported workouts on the next health sync', function (): void {
    $workout = WorkoutEntry::query()->create([
        'date' => '2026-05-19',
        'title' => 'Imported run',
        'calories_burned' => 250,
        'logged_at' => '2026-05-19 07:45:00',
        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
        'external_id' => 'health-workout-1',
    ]);

    $this->put("/workouts/{$workout->id}", [
        'date' => '2026-05-19',
        'title' => 'Edited run',
        'calories_burned' => 275,
        'time' => '08:00',
    ])->assertRedirect('/?date=2026-05-19');

    expect($workout->refresh()->source_type)->toBe(WorkoutEntry::SOURCE_MANUAL);
    $this->assertDatabaseHas('health_connect_ignored_workouts', ['external_id' => 'health-workout-1']);
});

it('opens the add page in workout mode', function (): void {
    $this->get('/add?mode=workout')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Add')
            ->where('mode', 'workout')
        );
});

it('rejects invalid manual workout creation without writing a record or sync change', function (array $payload, array $errors): void {
    Http::preventStrayRequests();

    $this->from('/add?mode=workout&date=2026-05-19')
        ->post('/workouts', $payload)
        ->assertRedirect('/add?mode=workout&date=2026-05-19')
        ->assertSessionHasErrors($errors);

    $this->assertDatabaseEmpty('workout_entries');
    $this->assertDatabaseEmpty('sync_outboxes');
    $this->assertDatabaseEmpty('health_connect_ignored_workouts');
})->with('invalid manual workout requests');

it('rejects invalid manual workout edits without changing the record or sync outbox', function (array $payload, array $errors): void {
    Http::preventStrayRequests();
    $workout = WorkoutEntry::query()->create([
        'date' => '2026-05-18',
        'title' => 'Original workout',
        'calories_burned' => 150,
        'logged_at' => '2026-05-18 06:30:00',
        'source_type' => WorkoutEntry::SOURCE_MANUAL,
    ]);
    $original = $workout->getRawOriginal();
    SyncOutbox::query()->delete();

    $this->from('/?date=2026-05-18')
        ->put("/workouts/{$workout->id}", $payload)
        ->assertRedirect('/?date=2026-05-18')
        ->assertSessionHasErrors($errors);

    $this->assertDatabaseCount('workout_entries', 1);
    $this->assertDatabaseHas('workout_entries', $original);
    $this->assertDatabaseEmpty('sync_outboxes');
    $this->assertDatabaseEmpty('health_connect_ignored_workouts');
})->with('invalid manual workout requests');

it('creates manual workouts at the allowed boundaries on future dates', function (array $payload, string $storedDate): void {
    Http::preventStrayRequests();
    $this->travelTo('2026-09-04 12:00:00');

    $this->post('/workouts', $payload)
        ->assertRedirect('/?date='.$payload['date'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('message', 'Workout added.');

    $this->assertDatabaseCount('workout_entries', 1);
    $this->assertDatabaseHas('workout_entries', [
        'date' => $storedDate.' 00:00:00',
        'title' => $payload['title'],
        'calories_burned' => $payload['calories_burned'],
        'logged_at' => $storedDate.' '.$payload['time'].':00',
        'source_type' => WorkoutEntry::SOURCE_MANUAL,
    ]);
    $workout = WorkoutEntry::query()->sole();
    $outbox = SyncOutbox::query()->sole();

    expect($outbox->record_id)->toBe($workout->id)
        ->and($outbox->is_deleted)->toBeFalse()
        ->and($outbox->payload)->toMatchArray([
            'date' => $storedDate,
            'title' => $payload['title'],
            'calories_burned' => $payload['calories_burned'],
            'source_type' => WorkoutEntry::SOURCE_MANUAL,
        ]);
    $this->assertDatabaseEmpty('health_connect_ignored_workouts');
})->with('valid manual workout boundaries');

it('updates manual workouts at the allowed boundaries on future dates', function (array $payload, string $storedDate): void {
    Http::preventStrayRequests();
    $this->travelTo('2026-09-04 12:00:00');
    $workout = WorkoutEntry::query()->create([
        'date' => '2026-05-18',
        'title' => 'Original workout',
        'calories_burned' => 150,
        'logged_at' => '2026-05-18 06:30:00',
        'source_type' => WorkoutEntry::SOURCE_MANUAL,
    ]);

    $this->put("/workouts/{$workout->id}", $payload)
        ->assertRedirect('/?date='.$payload['date'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('message', 'Workout updated.');

    $this->assertDatabaseCount('workout_entries', 1);
    $this->assertDatabaseHas('workout_entries', [
        'id' => $workout->id,
        'date' => $storedDate.' 00:00:00',
        'title' => $payload['title'],
        'calories_burned' => $payload['calories_burned'],
        'logged_at' => $storedDate.' '.$payload['time'].':00',
        'source_type' => WorkoutEntry::SOURCE_MANUAL,
    ]);
    $outbox = SyncOutbox::query()->sole();

    expect($outbox->record_id)->toBe($workout->id)
        ->and($outbox->is_deleted)->toBeFalse()
        ->and($outbox->payload)->toMatchArray([
            'date' => $storedDate,
            'title' => $payload['title'],
            'calories_burned' => $payload['calories_burned'],
            'source_type' => WorkoutEntry::SOURCE_MANUAL,
        ]);
    $this->assertDatabaseEmpty('health_connect_ignored_workouts');
})->with('valid manual workout boundaries');

$validManualWorkoutRequest = [
    'date' => '2026-05-19',
    'title' => 'Evening run',
    'calories_burned' => 250,
    'time' => '18:30',
];

dataset('invalid manual workout requests', [
    'missing required fields' => [[], [
        'date' => 'The date field is required.',
        'title' => 'The title field is required.',
        'calories_burned' => 'The calories burned field is required.',
        'time' => 'The time field is required.',
    ]],
    'blank title' => [array_replace($validManualWorkoutRequest, ['title' => '   ']), [
        'title' => 'The title field is required.',
    ]],
    'non-string title' => [array_replace($validManualWorkoutRequest, ['title' => ['Run']]), [
        'title' => 'The title field must be a string.',
    ]],
    'title longer than 120 characters' => [array_replace($validManualWorkoutRequest, ['title' => str_repeat('W', 121)]), [
        'title' => 'The title field must not be greater than 120 characters.',
    ]],
    'zero calories' => [array_replace($validManualWorkoutRequest, ['calories_burned' => 0]), [
        'calories_burned' => 'The calories burned field must be at least 1.',
    ]],
    'calories above 10000' => [array_replace($validManualWorkoutRequest, ['calories_burned' => 10001]), [
        'calories_burned' => 'The calories burned field must not be greater than 10000.',
    ]],
    'fractional calories' => [array_replace($validManualWorkoutRequest, ['calories_burned' => 250.5]), [
        'calories_burned' => 'The calories burned field must be an integer.',
    ]],
    'nonnumeric calories' => [array_replace($validManualWorkoutRequest, ['calories_burned' => 'many']), [
        'calories_burned' => 'The calories burned field must be an integer.',
    ]],
    'time without a leading zero' => [array_replace($validManualWorkoutRequest, ['time' => '7:45']), [
        'time' => 'The time field must match the format H:i.',
    ]],
    'time with seconds' => [array_replace($validManualWorkoutRequest, ['time' => '07:45:00']), [
        'time' => 'The time field must match the format H:i.',
    ]],
    'invalid hour' => [array_replace($validManualWorkoutRequest, ['time' => '24:00']), [
        'time' => 'The time field must match the format H:i.',
    ]],
    'invalid minute' => [array_replace($validManualWorkoutRequest, ['time' => '07:60']), [
        'time' => 'The time field must match the format H:i.',
    ]],
    'unparseable date' => [array_replace($validManualWorkoutRequest, ['date' => 'not-a-date']), [
        'date' => 'The date field must be a valid date.',
    ]],
    'impossible calendar date' => [array_replace($validManualWorkoutRequest, ['date' => '2026-02-30']), [
        'date' => 'The date field must be a valid date.',
    ]],
]);

dataset('valid manual workout boundaries', [
    'minimum calories at midnight' => [[
        'date' => '2026-09-05',
        'title' => 'W',
        'calories_burned' => 1,
        'time' => '00:00',
    ], '2026-09-05'],
    'maximum title and calories at 23:59 with a parseable non-ISO date' => [[
        'date' => '2026/09/06',
        'title' => str_repeat('W', 120),
        'calories_burned' => 10000,
        'time' => '23:59',
    ], '2026-09-06'],
]);
