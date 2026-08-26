<?php

namespace App\Http\Controllers;

use App\ActivityLevel;
use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\BodyProfile;
use App\Models\DailyGoal;
use App\Services\EnergyEstimator;
use App\Services\NutritionCalculator;
use App\Sex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    private const DEFAULT_GOAL = [
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
    ];

    public function create(): Response|RedirectResponse
    {
        if (DailyGoal::query()->exists()) {
            return redirect('/');
        }

        $profile = BodyProfile::query()->find(BodyProfile::ID);

        return Inertia::render('Onboarding', [
            'defaults' => [
                ...self::DEFAULT_GOAL,
                ...($profile?->toPayload() ?? [
                    'height_cm' => null,
                    'age' => null,
                    'sex' => null,
                    'activity_level' => null,
                ]),
                'current_weight_kg' => null,
                'target_weight_kg' => null,
                'target_body_fat_percent' => null,
                'weight_unit' => 'kg',
                'height_unit' => 'cm',
            ],
        ]);
    }

    public function plan(Request $request, EnergyEstimator $estimator, NutritionCalculator $calculator): JsonResponse
    {
        $validated = $request->validate([
            ...BodyProfile::rules(),
            'current_weight_kg' => ['required', 'numeric', 'min:1', 'max:1000'],
            'goal' => ['required', Rule::in(['lose', 'maintain', 'gain'])],
            'weekly_goal_kg' => [
                Rule::requiredIf(fn (): bool => $request->integer('age') >= 18 && $request->string('goal')->toString() !== 'maintain'),
                'nullable',
                'numeric',
                'min:0.05',
                'max:1',
            ],
        ]);

        $estimate = $estimator->dailyCalories(
            $validated['current_weight_kg'],
            $validated['height_cm'] ?? null,
            $validated['age'] ?? null,
            isset($validated['sex']) ? Sex::from($validated['sex']) : null,
            isset($validated['activity_level']) ? ActivityLevel::from($validated['activity_level']) : null,
            $validated['goal'],
            $validated['weekly_goal_kg'] ?? null,
        );

        if ($estimate === null) {
            return response()->json([
                ...self::DEFAULT_GOAL,
                'macro_calories' => self::DEFAULT_GOAL['calories'],
                'maintenance_calories' => null,
                'personalized' => false,
                'teen_maintenance_only' => false,
                'notice' => 'Complete age, sex, height, and activity for a personalized estimate, or start with these editable defaults.',
            ]);
        }

        return response()->json([
            ...$calculator->dailyGoalForCalories($estimate['calories']),
            'maintenance_calories' => $estimate['maintenance_calories'],
            'personalized' => true,
            'teen_maintenance_only' => $estimate['teen_maintenance_only'],
            'notice' => $estimate['teen_maintenance_only']
                ? 'For ages 13–17, Buff recommends maintenance calories only. Ask a parent or guardian and a qualified health professional about weight-change goals.'
                : 'This is a starting estimate. Track your progress and adjust it in Goals as needed.',
        ]);
    }

    public function store(Request $request, NutritionCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'calories' => ['required', 'integer', 'min:1', 'max:20000'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'carbs_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            ...BodyProfile::rules(),
            'current_weight_kg' => ['required', 'numeric', 'min:1', 'max:1000'],
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

        DB::transaction(function () use ($validated, $macroCalories): void {
            DailyGoal::query()->firstOrCreate([], [
                'calories' => $validated['calories'],
                'protein_g' => $validated['protein_g'],
                'carbs_g' => $validated['carbs_g'],
                'fat_g' => $validated['fat_g'],
                'macro_calories' => $macroCalories,
                'target_weight_kg' => $validated['target_weight_kg'] ?? null,
                'target_body_fat_percent' => $validated['target_body_fat_percent'] ?? null,
            ]);

            BodyProfile::current()->update([
                'height_cm' => $validated['height_cm'] ?? null,
                'age' => $validated['age'] ?? null,
                'sex' => $validated['sex'] ?? null,
                'activity_level' => $validated['activity_level'] ?? null,
            ]);

            AppPreference::current()->update([
                'weight_unit' => $validated['weight_unit'],
                'height_unit' => $validated['height_unit'],
                'measurement_unit' => $validated['height_unit'],
            ]);

            BodyMetric::query()->updateOrCreate(
                ['date' => today()->startOfDay()],
                [
                    'weight_kg' => $validated['current_weight_kg'],
                    'body_fat_percent' => null,
                    'notes' => null,
                ]
            );
        });

        return redirect('/')->with('message', 'Buff is ready.');
    }
}
