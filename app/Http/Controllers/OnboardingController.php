<?php

namespace App\Http\Controllers;

use App\Models\AppPreference;
use App\Models\DailyGoal;
use App\Models\SyncState;
use App\Services\BuffCredentialStore;
use App\Services\NutritionCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(BuffCredentialStore $credentials): Response|RedirectResponse
    {
        if ($credentials->token() === null && ($credentials->account() !== null || SyncState::query()->exists())) {
            return redirect()->route('account.login');
        }

        if (DailyGoal::query()->exists()) {
            return redirect('/');
        }

        return Inertia::render('Onboarding', [
            'defaults' => [
                'calories' => 2000,
                'protein_g' => 170,
                'carbs_g' => 195,
                'fat_g' => 60,
                'height_cm' => null,
                'target_weight_kg' => null,
                'target_body_fat_percent' => null,
                'weight_unit' => 'kg',
                'height_unit' => 'cm',
            ],
        ]);
    }

    public function store(Request $request, NutritionCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'calories' => ['required', 'integer', 'min:1', 'max:20000'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'carbs_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'height_cm' => ['nullable', 'numeric', 'min:50', 'max:260'],
            'target_weight_kg' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'target_body_fat_percent' => ['nullable', 'numeric', 'min:1', 'max:80'],
            'weight_unit' => ['required', Rule::in(AppPreference::WEIGHT_UNITS)],
            'height_unit' => ['required', Rule::in(AppPreference::HEIGHT_UNITS)],
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

        DailyGoal::query()->firstOrCreate([], [
            'calories' => $validated['calories'],
            'protein_g' => $validated['protein_g'],
            'carbs_g' => $validated['carbs_g'],
            'fat_g' => $validated['fat_g'],
            'macro_calories' => $macroCalories,
            'height_cm' => $validated['height_cm'] ?? null,
            'target_weight_kg' => $validated['target_weight_kg'] ?? null,
            'target_body_fat_percent' => $validated['target_body_fat_percent'] ?? null,
        ]);

        AppPreference::current()->update([
            'weight_unit' => $validated['weight_unit'],
            'height_unit' => $validated['height_unit'],
        ]);

        return redirect('/')->with('message', 'Buff is ready.');
    }
}
