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
    ]);

    $this->assertDatabaseCount('body_metrics', 1);
    $this->assertDatabaseHas('body_metrics', [
        'weight_kg' => 90,
    ]);
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
