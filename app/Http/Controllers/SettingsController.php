<?php

namespace App\Http\Controllers;

use App\Models\AppPreference;
use App\Models\DailyGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        $goal = DailyGoal::query()->latest('updated_at')->first();
        $preferences = AppPreference::current();

        return Inertia::render('Settings', [
            'settings' => $goal ? [
                'height_cm' => $goal->height_cm !== null ? (float) $goal->height_cm : null,
                'target_weight_kg' => $goal->target_weight_kg !== null ? (float) $goal->target_weight_kg : null,
                'target_body_fat_percent' => $goal->target_body_fat_percent !== null ? (float) $goal->target_body_fat_percent : null,
            ] : [
                'height_cm' => null,
                'target_weight_kg' => null,
                'target_body_fat_percent' => null,
            ],
            'preferences' => [
                'weight_unit' => $preferences->weight_unit,
                'height_unit' => $preferences->height_unit,
            ],
            'healthConnect' => HealthConnectController::sharedStatus(),
        ]);
    }

    public function updateUnits(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'weight_unit' => ['required', Rule::in(AppPreference::WEIGHT_UNITS)],
            'height_unit' => ['required', Rule::in(AppPreference::HEIGHT_UNITS)],
        ]);

        AppPreference::current()->update($validated);

        return back()->with('message', 'Unit preferences saved.');
    }

    public function updateBodyTargets(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_weight_kg' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'target_body_fat_percent' => ['nullable', 'numeric', 'min:1', 'max:80'],
        ]);

        $this->goal()->update($validated);

        return back()->with('message', 'Body targets saved.');
    }

    public function updateHeight(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'height_cm' => ['nullable', 'numeric', 'min:50', 'max:260'],
        ]);

        $this->goal()->update([
            'height_cm' => $validated['height_cm'] ?? null,
        ]);

        return back()->with('message', 'Height updated.');
    }

    private function goal(): DailyGoal
    {
        return DailyGoal::query()->firstOrCreate([], [
            'calories' => 2000,
            'protein_g' => 170,
            'carbs_g' => 195,
            'fat_g' => 60,
            'macro_calories' => 2000,
        ]);
    }
}
