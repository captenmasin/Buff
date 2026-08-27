<?php

use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\BodyProfile;
use App\Models\DailyGoal;
use App\Models\SyncOutbox;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

it('creates and updates a body metric for a date', function (): void {
    $this->from('/progress')
        ->post('/progress/body-metrics', [
            'date' => '2026-05-19',
            'weight_kg' => 82.4,
            'body_fat_percent' => 18.5,
            'chest_cm' => 102.4,
            'waist_cm' => 84.2,
            'notes' => 'Morning weigh-in',
        ])
        ->assertRedirect('/progress?range=90');

    $this->assertDatabaseHas('body_metrics', [
        'date' => '2026-05-19 00:00:00',
        'weight_kg' => 82.4,
        'body_fat_percent' => 18.5,
        'chest_cm' => 102.4,
        'waist_cm' => 84.2,
    ]);

    $this->from('/progress')
        ->post('/progress/body-metrics', [
            'date' => '2026-05-19',
            'weight_kg' => 82.0,
            'body_fat_percent' => 18.2,
        ])
        ->assertRedirect('/progress?range=90');

    $this->assertDatabaseCount('body_metrics', 1);
    $this->assertDatabaseHas('body_metrics', [
        'date' => '2026-05-19 00:00:00',
        'weight_kg' => 82.0,
        'body_fat_percent' => 18.2,
        'chest_cm' => 102.4,
        'waist_cm' => 84.2,
    ]);

    $outbox = SyncOutbox::query()->where('record_type', 'body_metrics')->sole();

    expect($outbox->payload['chest_cm'])->toBe('102.40')
        ->and($outbox->payload['waist_cm'])->toBe('84.20');
});

it('uses the most recent weight when the weight field is empty', function (): void {
    BodyMetric::query()->create([
        'date' => '2026-05-18',
        'weight_kg' => 82.4,
    ]);

    BodyMetric::query()->create([
        'date' => '2026-05-20',
        'weight_kg' => 81.6,
    ]);

    $this->post('/progress/body-metrics', [
        'date' => '2026-05-21',
        'weight_kg' => '',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('body_metrics', [
        'date' => '2026-05-21 00:00:00',
        'weight_kg' => 81.6,
    ]);
});

it('enforces one body metric per date at the database boundary', function (): void {
    BodyMetric::query()->create([
        'date' => '2026-05-19',
        'weight_kg' => 82.4,
    ]);

    expect(fn () => BodyMetric::query()->create([
        'date' => '2026-05-19',
        'weight_kg' => 81.9,
    ]))->toThrow(QueryException::class);
});

it('validates body measurements', function (string $field, mixed $value): void {
    $this->post('/progress/body-metrics', [
        'date' => '2026-05-19',
        'weight_kg' => 82.4,
        $field => $value,
    ])->assertSessionHasErrors($field);
})->with([
    'chest below range' => ['chest_cm', 0],
    'waist above range' => ['waist_cm', 501],
    'hips nonnumeric' => ['hips_cm', 'wide'],
    'upper arm below range' => ['upper_arm_cm', 0],
    'thigh above range' => ['thigh_cm', 501],
]);

it('reloads recent history after saving a measurement', function (): void {
    Date::setTestNow('2026-08-20');

    $this->from('/progress?range=30')
        ->post('/progress/body-metrics?range=30', [
            'date' => '2026-08-20',
            'weight_kg' => 81.2,
            'body_fat_percent' => 18.1,
            'notes' => 'After lift',
        ])
        ->assertRedirect('/progress?range=30');

    $this->get('/progress?range=30')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Progress')
            ->where('range', '30')
            ->has('history', 1)
            ->where('history.0.date', '2026-08-20')
            ->where('history.0.weight_kg', 81.2)
            ->where('history.0.body_fat_percent', 18.1)
            ->where('history.0.notes', 'After lift')
            ->where('latest.weight_kg', 81.2)
        );

    $this->from('/progress?range=30')
        ->post('/progress/body-metrics?range=30', [
            'date' => '2026-08-18',
            'weight_kg' => 81.6,
        ])
        ->assertRedirect('/progress?range=30');

    $this->get('/progress?range=30')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('history', 2)
            ->where('history.0.date', '2026-08-20')
            ->where('history.1.date', '2026-08-18')
            ->where('history.1.weight_kg', 81.6)
            ->where('latest.weight_kg', 81.2)
        );

    Date::setTestNow();
});

it('renders latest metric delta and history', function (): void {
    Date::setTestNow('2026-05-19');

    BodyMetric::query()->create([
        'date' => '2026-05-18',
        'weight_kg' => 83.0,
        'body_fat_percent' => 19.0,
        'chest_cm' => 103.0,
        'waist_cm' => 85.0,
    ]);

    BodyMetric::query()->create([
        'date' => '2026-05-19',
        'weight_kg' => 82.4,
        'body_fat_percent' => 18.5,
        'chest_cm' => 104.2,
    ]);

    $this->get('/progress')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Progress')
            ->where('latest.weight_kg', 82.4)
            ->where('delta.weight_kg', -0.6)
            ->where('delta.body_fat_percent', -0.5)
            ->where('measurements.chest_cm.value_cm', 104.2)
            ->where('measurements.chest_cm.delta_cm', 1.2)
            ->where('measurements.waist_cm.value_cm', 85)
            ->where('measurements.waist_cm.delta_cm', null)
            ->where('measurements.hips_cm', null)
            ->where('range', '90')
            ->where('trend.weight_kg', 82.85)
            ->where('trend.delta_kg', -0.15)
            ->missing('trend.to_goal_kg')
            ->has('history', 2)
        );

    Date::setTestNow();
});

it('filters progress history by calendar range', function (): void {
    Date::setTestNow('2026-08-20');

    BodyMetric::query()->create(['date' => '2026-08-20', 'weight_kg' => 80]);
    BodyMetric::query()->create(['date' => '2026-08-17', 'weight_kg' => 80.4]);
    BodyMetric::query()->create(['date' => '2026-07-11', 'weight_kg' => 81]);
    BodyMetric::query()->create(['date' => '2026-05-12', 'weight_kg' => 82]);

    $this->get('/progress')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('range', '90')
            ->has('history', 3)
            ->where('latest.weight_kg', 80)
        );

    $this->get('/progress?range=30')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('range', '30')
            ->has('history', 2)
            ->where('latest.weight_kg', 80)
        );

    $this->get('/progress?range=all')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('range', 'all')
            ->has('history', 4)
        );

    $this->get('/progress?range=7')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('range', '90')
            ->has('history', 3)
        );

    Date::setTestNow();
});

it('passes body profile and goals to progress', function (): void {
    AppPreference::current()->update([
        'weight_unit' => 'lb',
        'height_unit' => 'in',
        'measurement_unit' => 'in',
    ]);

    BodyProfile::current()->update([
        'height_cm' => 178,
        'age' => 32,
        'sex' => 'male',
        'activity_level' => 'moderate',
    ]);

    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
        'target_weight_kg' => 80,
        'target_body_fat_percent' => 15,
    ]);

    $this->get('/progress')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.height_cm', 178)
            ->where('profile.age', 32)
            ->where('profile.sex', 'male')
            ->where('profile.activity_level', 'moderate')
            ->where('goals.target_weight_kg', 80)
            ->where('goals.target_body_fat_percent', 15)
            ->where('energy', null)
            ->where('preferences.weight_unit', 'lb')
            ->where('preferences.height_unit', 'in')
            ->where('preferences.measurement_unit', 'in')
        );
});

it('deletes a body metric', function (): void {
    $metric = BodyMetric::query()->create([
        'date' => '2026-05-19',
        'weight_kg' => 82.4,
    ]);

    $this->delete("/progress/body-metrics/{$metric->id}")
        ->assertRedirect('/progress?range=90');

    $this->assertDatabaseMissing('body_metrics', ['id' => $metric->id]);
});

it('estimates energy when the body profile and a weigh-in are complete', function (): void {
    BodyMetric::query()->create([
        'date' => '2026-05-19',
        'weight_kg' => 80,
    ]);

    BodyProfile::current()->update([
        'height_cm' => 178,
        'age' => 30,
        'sex' => 'male',
        'activity_level' => 'moderate',
    ]);

    $this->get('/progress')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('energy.bmr', 1768)
            ->where('energy.tdee', 2740)
        );
});

it('removes the old body profile endpoints', function (): void {
    $this->put('/settings/body-targets')->assertNotFound();
    $this->put('/settings/height')->assertNotFound();
    $this->put('/progress/height')->assertNotFound();
    $this->put('/progress/body-profile')->assertNotFound();
});
