<?php

use App\Http\Middleware\EnsureBuffAccount;
use App\Models\BodyProfile;
use App\Models\DailyGoal;
use App\Models\SyncState;
use App\Services\BuffCredentialStore;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withMiddleware(EnsureBuffAccount::class);
    SyncState::query()->delete();
    app(BuffCredentialStore::class)->store('onboarding-token', [
        'id' => '1', 'name' => 'Mason', 'email' => 'mason@example.com', 'timezone' => 'Europe/London', 'email_verified' => false,
    ]);
    SyncState::current('1');
});

it('renders onboarding for an authenticated user', function (): void {
    $this->get('/onboarding')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding')
            ->where('defaults.calories', 2000)
            ->where('defaults.weight_unit', 'kg')
        );
});

it('recommends a personalized adult plan', function (): void {
    $this->postJson('/onboarding/plan', [
        'age' => 30,
        'sex' => 'male',
        'height_cm' => 178,
        'activity_level' => 'moderate',
        'current_weight_kg' => 80,
        'goal' => 'lose',
        'weekly_goal_kg' => 0.5,
    ])->assertOk()
        ->assertJsonPath('personalized', true)
        ->assertJsonPath('teen_maintenance_only', false)
        ->assertJsonPath('maintenance_calories', 2750)
        ->assertJsonPath('calories', 2200)
        ->assertJsonPath('protein_g', 165)
        ->assertJsonPath('carbs_g', 220)
        ->assertJsonPath('macro_calories', 2200);
});

it('keeps teen recommendations at maintenance', function (): void {
    $this->postJson('/onboarding/plan', [
        'age' => 16,
        'sex' => 'female',
        'height_cm' => 170,
        'activity_level' => 'moderate',
        'current_weight_kg' => 60,
        'goal' => 'lose',
    ])->assertOk()
        ->assertJsonPath('personalized', true)
        ->assertJsonPath('teen_maintenance_only', true)
        ->assertJsonPath('maintenance_calories', 2550)
        ->assertJsonPath('calories', 2550);
});

it('falls back to editable defaults when profile data is incomplete', function (): void {
    $this->postJson('/onboarding/plan', [
        'current_weight_kg' => 80,
        'goal' => 'maintain',
    ])->assertOk()
        ->assertJsonPath('personalized', false)
        ->assertJsonPath('maintenance_calories', null)
        ->assertJsonPath('calories', 2000)
        ->assertJsonPath('protein_g', 170)
        ->assertJsonPath('carbs_g', 195)
        ->assertJsonPath('fat_g', 60);
});

it('stores the initial profile and preferences', function (): void {
    $this->post('/onboarding', [
        'calories' => 2000,
        'protein_g' => 150,
        'carbs_g' => 200,
        'fat_g' => 66.6667,
        'height_cm' => 180,
        'age' => 29,
        'sex' => 'male',
        'activity_level' => 'moderate',
        'current_weight_kg' => 90,
        'target_weight_kg' => 82,
        'target_body_fat_percent' => 15,
        'weight_unit' => 'lb',
        'height_unit' => 'in',
    ])->assertRedirect('/');

    $this->assertDatabaseHas('daily_goals', [
        'calories' => 2000,
        'target_weight_kg' => 82,
    ]);

    $this->assertDatabaseHas('body_profiles', [
        'id' => BodyProfile::ID,
        'height_cm' => 180,
        'age' => 29,
        'sex' => 'male',
        'activity_level' => 'moderate',
    ]);

    $this->assertDatabaseHas('app_preferences', [
        'weight_unit' => 'lb',
        'height_unit' => 'in',
        'measurement_unit' => 'in',
    ]);

    $this->assertDatabaseCount('body_metrics', 1);
    $this->assertDatabaseHas('body_metrics', [
        'weight_kg' => 90,
    ]);
});

it('rolls back every onboarding write when completion fails', function (): void {
    BodyProfile::creating(function (): void {
        throw new RuntimeException('Simulated profile write failure.');
    });

    try {
        expect(fn () => $this->withoutExceptionHandling()->post('/onboarding', [
            'calories' => 2000,
            'protein_g' => 150,
            'carbs_g' => 200,
            'fat_g' => 66.6667,
            'current_weight_kg' => 90,
            'weight_unit' => 'kg',
            'height_unit' => 'cm',
        ]))->toThrow(RuntimeException::class, 'Simulated profile write failure.');
    } finally {
        BodyProfile::flushEventListeners();
    }

    $this->assertDatabaseEmpty('daily_goals');
    $this->assertDatabaseEmpty('body_profiles');
    $this->assertDatabaseEmpty('app_preferences');
    $this->assertDatabaseEmpty('body_metrics');
});

it('requires current weight during onboarding', function (): void {
    $this->post('/onboarding', [
        'calories' => 2000,
        'protein_g' => 150,
        'carbs_g' => 200,
        'fat_g' => 66.6667,
        'height_cm' => 180,
        'age' => 29,
        'sex' => 'male',
        'activity_level' => 'moderate',
        'target_weight_kg' => 82,
        'weight_unit' => 'kg',
        'height_unit' => 'cm',
    ])->assertSessionHasErrors('current_weight_kg');

    $this->assertDatabaseCount('body_metrics', 0);
});

it('does not require a target weight', function (): void {
    $this->post('/onboarding', [
        'calories' => 2000,
        'protein_g' => 150,
        'carbs_g' => 200,
        'fat_g' => 66.6667,
        'current_weight_kg' => 90,
        'weight_unit' => 'kg',
        'height_unit' => 'cm',
    ])->assertRedirect('/');

    $this->assertDatabaseHas('body_metrics', [
        'weight_kg' => 90,
    ]);

    expect(DailyGoal::query()->value('target_weight_kg'))->toBeNull();
});

it('redirects onboarding away once goals exist', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    $this->get('/onboarding')
        ->assertRedirect('/');
});
