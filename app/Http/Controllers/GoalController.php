<?php

namespace App\Http\Controllers;

use App\Models\AppPreference;
use App\Models\DailyGoal;
use App\Services\NutritionCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function edit(): Response
    {
        $goal = DailyGoal::query()->latest('updated_at')->first();
        $preferences = AppPreference::current();

        return Inertia::render('Goals', [
            'preferences' => [
                'weight_unit' => $preferences->weight_unit,
            ],
            'goal' => $goal ? [
                'calories' => $goal->calories,
                'protein_g' => (float) $goal->protein_g,
                'carbs_g' => (float) $goal->carbs_g,
                'fat_g' => (float) $goal->fat_g,
                'macro_calories' => $goal->macro_calories,
                'target_weight_kg' => $goal->target_weight_kg !== null ? (float) $goal->target_weight_kg : null,
                'target_body_fat_percent' => $goal->target_body_fat_percent !== null ? (float) $goal->target_body_fat_percent : null,
            ] : [
                'calories' => 2000,
                'protein_g' => 170,
                'carbs_g' => 195,
                'fat_g' => 60,
                'macro_calories' => 2000,
                'target_weight_kg' => null,
                'target_body_fat_percent' => null,
            ],
        ]);
    }

    public function update(Request $request, NutritionCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'calories' => ['required', 'integer', 'min:1', 'max:20000'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'carbs_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'target_weight_kg' => ['present', 'nullable', 'numeric', 'min:1', 'max:1000'],
            'target_body_fat_percent' => ['present', 'nullable', 'numeric', 'min:1', 'max:80'],
        ]);

        $macroCalories = $calculator->macroCalories(
            $validated['protein_g'],
            $validated['carbs_g'],
            $validated['fat_g']
        );

        if (! $calculator->goalMatchesCalories($validated['calories'], $validated['protein_g'], $validated['carbs_g'], $validated['fat_g'])) {
            throw ValidationException::withMessages([
                'calories' => "Macro calories must equal your calorie goal. Current macro total is {$macroCalories} kcal.",
            ]);
        }

        $values = [
            ...$validated,
            'macro_calories' => $macroCalories,
        ];

        $goal = DailyGoal::query()->latest('updated_at')->first();

        $goal
            ? $goal->update($values)
            : DailyGoal::query()->create($values);

        return redirect('/goals')->with('message', 'Daily goals saved.');
    }
}
