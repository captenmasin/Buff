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
