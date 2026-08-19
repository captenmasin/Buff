<?php

namespace App\Http\Controllers;

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

        return Inertia::render('Goals', [
            'goal' => $goal ? [
                'calories' => $goal->calories,
                'protein_g' => (float) $goal->protein_g,
                'carbs_g' => (float) $goal->carbs_g,
                'fat_g' => (float) $goal->fat_g,
                'macro_calories' => $goal->macro_calories,
            ] : [
                'calories' => 2000,
                'protein_g' => 170,
                'carbs_g' => 195,
                'fat_g' => 60,
                'macro_calories' => 2000,
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

        $goal = DailyGoal::query()->first();

        $goal
            ? $goal->update($values)
            : DailyGoal::query()->create($values);

        return redirect('/goals')->with('message', 'Daily goals saved.');
    }
}
