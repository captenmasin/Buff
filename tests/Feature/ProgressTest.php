<?php

namespace Tests\Feature;

use App\Models\BodyMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_updates_a_body_metric_for_a_date(): void
    {
        $this->post('/progress/body-metrics', [
            'date' => '2026-05-19',
            'weight_kg' => 82.4,
            'body_fat_percent' => 18.5,
            'notes' => 'Morning weigh-in',
        ])->assertRedirect();

        $this->assertDatabaseHas('body_metrics', [
            'date' => '2026-05-19 00:00:00',
            'weight_kg' => 82.4,
            'body_fat_percent' => 18.5,
        ]);

        $this->post('/progress/body-metrics', [
            'date' => '2026-05-19',
            'weight_kg' => 82.0,
            'body_fat_percent' => 18.2,
        ])->assertRedirect();

        $this->assertDatabaseCount('body_metrics', 1);
        $this->assertDatabaseHas('body_metrics', [
            'date' => '2026-05-19 00:00:00',
            'weight_kg' => 82.0,
            'body_fat_percent' => 18.2,
        ]);
    }

    public function test_it_renders_latest_metric_delta_and_history(): void
    {
        BodyMetric::query()->create([
            'date' => '2026-05-18',
            'weight_kg' => 83.0,
            'body_fat_percent' => 19.0,
        ]);

        BodyMetric::query()->create([
            'date' => '2026-05-19',
            'weight_kg' => 82.4,
            'body_fat_percent' => 18.5,
        ]);

        $this->get('/progress')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Progress')
                ->where('latest.weight_kg', 82.4)
                ->where('delta.weight_kg', -0.6)
                ->where('delta.body_fat_percent', -0.5)
                ->has('history', 2)
            );
    }
}
