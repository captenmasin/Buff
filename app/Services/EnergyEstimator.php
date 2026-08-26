<?php

namespace App\Services;

use App\ActivityLevel;
use App\Sex;

class EnergyEstimator
{
    private const CALORIES_PER_KILOGRAM = 7700;

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

    /**
     * @return array{maintenance_calories: int, calories: int, teen_maintenance_only: bool}|null
     */
    public function dailyCalories(
        float|int|string|null $weightKg,
        float|int|string|null $heightCm,
        int|string|null $age,
        ?Sex $sex,
        ?ActivityLevel $activityLevel,
        string $goal,
        float|int|string|null $weeklyGoalKg,
    ): ?array {
        $maintenance = $this->maintenanceCalories($weightKg, $heightCm, $age, $sex, $activityLevel);

        if ($maintenance === null) {
            return null;
        }

        $years = (int) $age;
        $teenMaintenanceOnly = $years < 18;
        $adjustment = $teenMaintenanceOnly || $goal === 'maintain'
            ? 0
            : min(((float) $weeklyGoalKg * self::CALORIES_PER_KILOGRAM) / 7, $maintenance * 0.2);

        /** ponytail: Linear weight-change math is a starting estimate; use a dynamic model if plan forecasting becomes a product feature. */
        $calories = match ($goal) {
            'lose' => $maintenance - $adjustment,
            'gain' => $maintenance + $adjustment,
            default => $maintenance,
        };

        return [
            'maintenance_calories' => $this->roundToFifty($maintenance),
            'calories' => $this->roundToFifty($calories),
            'teen_maintenance_only' => $teenMaintenanceOnly,
        ];
    }

    private function maintenanceCalories(
        float|int|string|null $weightKg,
        float|int|string|null $heightCm,
        int|string|null $age,
        ?Sex $sex,
        ?ActivityLevel $activityLevel,
    ): ?int {
        if ($weightKg === null || $heightCm === null || $age === null || $sex === null || $activityLevel === null) {
            return null;
        }

        $weight = (float) $weightKg;
        $height = (float) $heightCm;
        $years = (int) $age;

        if ($weight < 1 || $height < 1 || $years < 1) {
            return null;
        }

        if ($years <= 18) {
            return (int) round($this->adolescentEnergyRequirement($weight, $height, $years, $sex, $activityLevel));
        }

        return $this->estimate($weight, $height, $years, $sex, $activityLevel)['tdee'];
    }

    private function adolescentEnergyRequirement(
        float $weightKg,
        float $heightCm,
        int $age,
        Sex $sex,
        ActivityLevel $activityLevel,
    ): float {
        $growthCalories = $age <= 13
            ? match ($sex) {
                Sex::Male => 25,
                Sex::Female => 30,
            }
        : 20;

        return match ([$sex, $activityLevel]) {
            [Sex::Male, ActivityLevel::Sedentary] => -447.51 + (3.68 * $age) + (13.01 * $heightCm) + (13.15 * $weightKg) + $growthCalories,
            [Sex::Male, ActivityLevel::Light] => 19.12 + (3.68 * $age) + (8.62 * $heightCm) + (20.28 * $weightKg) + $growthCalories,
            [Sex::Male, ActivityLevel::Moderate] => -388.19 + (3.68 * $age) + (12.66 * $heightCm) + (20.46 * $weightKg) + $growthCalories,
            [Sex::Male, ActivityLevel::Active], [Sex::Male, ActivityLevel::VeryActive] => -671.75 + (3.68 * $age) + (15.38 * $heightCm) + (23.25 * $weightKg) + $growthCalories,
            [Sex::Female, ActivityLevel::Sedentary] => 55.59 - (22.25 * $age) + (8.43 * $heightCm) + (17.07 * $weightKg) + $growthCalories,
            [Sex::Female, ActivityLevel::Light] => -297.54 - (22.25 * $age) + (12.77 * $heightCm) + (14.73 * $weightKg) + $growthCalories,
            [Sex::Female, ActivityLevel::Moderate] => -189.55 - (22.25 * $age) + (11.74 * $heightCm) + (18.34 * $weightKg) + $growthCalories,
            [Sex::Female, ActivityLevel::Active], [Sex::Female, ActivityLevel::VeryActive] => -709.59 - (22.25 * $age) + (18.22 * $heightCm) + (14.25 * $weightKg) + $growthCalories,
        };
    }

    private function roundToFifty(float|int $calories): int
    {
        return (int) (round($calories / 50) * 50);
    }

    private function sexOffset(Sex $sex): int
    {
        return match ($sex) {
            Sex::Male => 5,
            Sex::Female => -161,
        };
    }
}
