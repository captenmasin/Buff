<?php

namespace App\Services;

use App\ActivityLevel;
use App\Sex;

class EnergyEstimator
{
    /**
     * @return array{bmr: int, tdee: int}|null
     */
    public function estimate(
        float|int|string|null $weightKg,
        float|int|string|null $heightCm,
        int|string|null $age,
        ?Sex $sex,
        ?ActivityLevel $activityLevel,
    ): ?array {
        if ($weightKg === null || $heightCm === null || $age === null || $sex === null || $activityLevel === null) {
            return null;
        }

        $weight = (float) $weightKg;
        $height = (float) $heightCm;
        $years = (int) $age;

        if ($weight < 1 || $height < 1 || $years < 1) {
            return null;
        }

        $bmr = (10 * $weight) + (6.25 * $height) - (5 * $years) + $this->sexOffset($sex);

        return [
            'bmr' => (int) round($bmr),
            'tdee' => (int) round($bmr * $activityLevel->multiplier()),
        ];
    }

    private function sexOffset(Sex $sex): int
    {
        return match ($sex) {
            Sex::Male => 5,
            Sex::Female => -161,
        };
    }
}
