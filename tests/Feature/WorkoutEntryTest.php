<?php

use App\Models\WorkoutEntry;
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
