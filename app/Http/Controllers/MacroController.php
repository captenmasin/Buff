<?php

namespace App\Http\Controllers;

use App\Models\DailyGoal;
use App\Models\MealEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MacroController extends Controller
{
    private const MACROS = [
        'protein' => [
            'key' => 'protein_g',
            'label' => 'Protein',
            'calorie_multiplier' => 4,
        ],
        'carbs' => [
            'key' => 'carbs_g',
            'label' => 'Carbs',
            'calorie_multiplier' => 4,
        ],
        'fat' => [
            'key' => 'fat_g',
            'label' => 'Fat',
            'calorie_multiplier' => 9,
        ],
    ];

    public function __invoke(Request $request, string $macro): Response
    {
        abort_unless(array_key_exists($macro, self::MACROS), 404);

        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : today();

        $macroDefinition = self::MACROS[$macro];
        $macroKey = $macroDefinition['key'];

        $goal = DailyGoal::query()
            ->latest('updated_at')
            ->first();

        $entries = MealEntry::query()
            ->with('foodProduct')
            ->whereDate('date', $date->toDateString())
            ->orderByDesc($macroKey)
            ->oldest()
            ->get();

        $totals = [
            'protein_g' => round((float) $entries->sum('protein_g'), 2),
            'carbs_g' => round((float) $entries->sum('carbs_g'), 2),
            'fat_g' => round((float) $entries->sum('fat_g'), 2),
        ];

        return Inertia::render('MacroBreakdown', [
            'date' => $date->toDateString(),
            'macro' => [
                'slug' => $macro,
                'key' => $macroKey,
                'label' => $macroDefinition['label'],
                'consumed_g' => $totals[$macroKey],
                'goal_g' => $goal ? (float) $goal->{$macroKey} : 0,
                'current_percentage' => $this->macroPercentage($totals, $macroKey),
                'goal_percentage' => $goal ? $this->goalPercentage($goal, $macroKey) : 0,
            ],
            'entries' => $entries
                ->map(fn (MealEntry $entry): array => [
                    'id' => $entry->id,
                    'meal_type' => $entry->meal_type,
                    'name' => $entry->name,
                    'brand' => $entry->foodProduct?->brand,
                    'image_url' => $entry->foodProduct?->image_url,
                    'portion_quantity' => $entry->portion_quantity !== null ? (float) $entry->portion_quantity : null,
                    'portion_unit' => $entry->portion_unit,
                    'calories' => $entry->calories,
                    'protein_g' => (float) $entry->protein_g,
                    'carbs_g' => (float) $entry->carbs_g,
                    'fat_g' => (float) $entry->fat_g,
                ])
                ->all(),
        ]);
    }

    /**
     * @param  array{protein_g: float, carbs_g: float, fat_g: float}  $totals
     */
    private function macroPercentage(array $totals, string $macroKey): int
    {
        $macroCalories = $this->macroCalories($totals);

        if ($macroCalories === 0) {
            return 0;
        }

        return (int) round(($totals[$macroKey] * self::multiplierFor($macroKey)) / $macroCalories * 100);
    }

    private function goalPercentage(DailyGoal $goal, string $macroKey): int
    {
        if ($goal->macro_calories === 0) {
            return 0;
        }

        return (int) round(((float) $goal->{$macroKey} * self::multiplierFor($macroKey)) / $goal->macro_calories * 100);
    }

    /**
     * @param  array{protein_g: float, carbs_g: float, fat_g: float}  $totals
     */
    private function macroCalories(array $totals): float
    {
        return ($totals['protein_g'] * 4) + ($totals['carbs_g'] * 4) + ($totals['fat_g'] * 9);
    }

    private static function multiplierFor(string $macroKey): int
    {
        return collect(self::MACROS)
            ->firstWhere('key', $macroKey)['calorie_multiplier'];
    }
}
