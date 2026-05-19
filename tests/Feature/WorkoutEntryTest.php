<?php

namespace Tests\Feature;

use App\Models\WorkoutEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkoutEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_workout_entry(): void
    {
        $this->post('/workouts', [
            'date' => '2026-05-19',
            'title' => 'Strength training',
            'calories_burned' => 250,
            'time' => '07:45',
        ])->assertRedirect('/?date=2026-05-19');

        $workout = WorkoutEntry::query()->first();

        $this->assertSame('Strength training', $workout->title);
        $this->assertSame(250, $workout->calories_burned);
        $this->assertSame('2026-05-19 07:45:00', $workout->logged_at->format('Y-m-d H:i:s'));
    }

    public function test_add_page_accepts_workout_mode(): void
    {
        $this->get('/add?mode=workout')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Add')
                ->where('mode', 'workout')
            );
    }
}
