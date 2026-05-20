<?php

namespace App\Services;

use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Models\WorkoutEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DailySummaryService
{
    public function forDate(CarbonInterface $date): array
    {
        $goal = DailyGoal::query()
            ->latest('updated_at')
            ->first();

        $entries = MealEntry::query()
            ->with('foodProduct')
            ->whereDate('date', $date->toDateString())
            ->oldest()
            ->get();

        $workouts = WorkoutEntry::query()
            ->whereDate('date', $date->toDateString())
            ->oldest('logged_at')
            ->get();

        $totals = $this->totals($entries);

        $calorieGoal = $goal?->calories ?? 0;
        $burned = (int) $workouts->sum('calories_burned');

        return [
            'date' => $date->toDateString(),
            'goal' => $goal ? [
                'calories' => $goal->calories,
                'protein_g' => (float) $goal->protein_g,
                'carbs_g' => (float) $goal->carbs_g,
                'fat_g' => (float) $goal->fat_g,
                'macro_calories' => $goal->macro_calories,
            ] : null,
            'log' => [
                'burned_calories' => $burned,
            ],
            'totals' => [
                ...$totals,
                'calories_remaining' => $calorieGoal + $burned - $totals['calories'],
                'protein_remaining' => $goal ? round((float) $goal->protein_g - $totals['protein_g'], 2) : 0,
                'carbs_remaining' => $goal ? round((float) $goal->carbs_g - $totals['carbs_g'], 2) : 0,
                'fat_remaining' => $goal ? round((float) $goal->fat_g - $totals['fat_g'], 2) : 0,
            ],
            'entries' => $entries
                ->groupBy('meal_type')
                ->map(fn (Collection $items): array => $items->map(fn (MealEntry $entry): array => [
                    'id' => $entry->id,
                    'name' => $entry->name,
                    'source_type' => $entry->source_type,
                    'portion_quantity' => $entry->portion_quantity !== null ? (float) $entry->portion_quantity : null,
                    'portion_unit' => $entry->portion_unit,
                    'calories' => $entry->calories,
                    'protein_g' => (float) $entry->protein_g,
                    'carbs_g' => (float) $entry->carbs_g,
                    'fat_g' => (float) $entry->fat_g,
                ])->all())
                ->all(),
            'workouts' => $workouts
                ->map(fn (WorkoutEntry $workout): array => [
                    'id' => $workout->id,
                    'title' => $workout->title,
                    'calories_burned' => $workout->calories_burned,
                    'logged_time' => $workout->logged_at?->format('H:i'),
                    'source_type' => $workout->source_type,
                    'external_source' => $workout->external_source,
                ])
                ->all(),
        ];
    }

    private function totals(Collection $entries): array
    {
        return [
            'calories' => (int) $entries->sum('calories'),
            'protein_g' => round((float) $entries->sum('protein_g'), 2),
            'carbs_g' => round((float) $entries->sum('carbs_g'), 2),
            'fat_g' => round((float) $entries->sum('fat_g'), 2),
        ];
    }
}
