<?php

use App\ActivityLevel;
use App\Services\EnergyEstimator;
use App\Sex;

it('estimates bmr and tdee with mifflin st jeor', function (): void {
    $estimator = new EnergyEstimator;

    expect($estimator->estimate(80, 178, 30, Sex::Male, ActivityLevel::Moderate))->toBe([
        'bmr' => 1768,
        'tdee' => 2740,
    ])->and($estimator->estimate(80, 178, 30, Sex::Female, ActivityLevel::Moderate))->toBe([
        'bmr' => 1602,
        'tdee' => 2482,
    ]);
});

it('returns null until weight height age sex and activity are all set', function (): void {
    $estimator = new EnergyEstimator;

    expect($estimator->estimate(null, 178, 30, Sex::Male, ActivityLevel::Moderate))->toBeNull()
        ->and($estimator->estimate(80, null, 30, Sex::Male, ActivityLevel::Moderate))->toBeNull()
        ->and($estimator->estimate(80, 178, null, Sex::Male, ActivityLevel::Moderate))->toBeNull()
        ->and($estimator->estimate(80, 178, 30, null, ActivityLevel::Moderate))->toBeNull()
        ->and($estimator->estimate(80, 178, 30, Sex::Male, null))->toBeNull();
});

it('recommends adult calories from maintenance and goal pace', function (): void {
    $estimator = new EnergyEstimator;

    expect($estimator->dailyCalories(80, 178, 30, Sex::Male, ActivityLevel::Moderate, 'lose', 0.5))->toBe([
        'maintenance_calories' => 2750,
        'calories' => 2200,
        'teen_maintenance_only' => false,
    ])->and($estimator->dailyCalories(80, 178, 30, Sex::Male, ActivityLevel::Moderate, 'gain', 0.25))->toBe([
        'maintenance_calories' => 2750,
        'calories' => 3000,
        'teen_maintenance_only' => false,
    ]);
});

it('uses adolescent energy requirements without a weight change adjustment', function (): void {
    $estimator = new EnergyEstimator;

    expect($estimator->dailyCalories(60, 170, 16, Sex::Male, ActivityLevel::Moderate, 'lose', 0.5))->toBe([
        'maintenance_calories' => 3050,
        'calories' => 3050,
        'teen_maintenance_only' => true,
    ])->and($estimator->dailyCalories(60, 170, 16, Sex::Female, ActivityLevel::Moderate, 'gain', 0.25))->toBe([
        'maintenance_calories' => 2550,
        'calories' => 2550,
        'teen_maintenance_only' => true,
    ]);
});
