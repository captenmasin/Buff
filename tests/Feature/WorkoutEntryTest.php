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

it('opens the add page in workout mode', function (): void {
    $this->get('/add?mode=workout')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Add')
            ->where('mode', 'workout')
        );
});
