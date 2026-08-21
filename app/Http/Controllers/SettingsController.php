<?php

namespace App\Http\Controllers;

use App\Models\AppPreference;
use App\Models\BodyProfile;
use App\Services\MealReminderBridge;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        $preferences = AppPreference::current();

        return Inertia::render('Settings', [
            'preferences' => [
                'weight_unit' => $preferences->weight_unit,
                'height_unit' => $preferences->height_unit,
                'eat_back' => $preferences->eatBack(),
            ],
            'bodyProfile' => BodyProfile::current()->toPayload(),
            'mealReminders' => $preferences->mealReminders(),
            'healthConnect' => HealthConnectController::sharedStatus(),
            'appleHealth' => AppleHealthController::sharedStatus(),
            'timezones' => timezone_identifiers_list(DateTimeZone::ALL),
        ]);
    }

    public function updateUnits(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'weight_unit' => ['required', Rule::in(AppPreference::WEIGHT_UNITS)],
            'height_unit' => ['required', Rule::in(AppPreference::HEIGHT_UNITS)],
        ]);

        AppPreference::current()->update($validated);

        return back();
    }

    public function updateEatBack(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'eat_back' => ['required', Rule::in(AppPreference::EAT_BACK)],
        ]);

        AppPreference::current()->update($validated);

        return back();
    }

    public function updateBodyProfile(Request $request): RedirectResponse
    {
        BodyProfile::current()->update($request->validate(BodyProfile::rules(present: true)));

        return back()->with('message', 'Body profile saved.');
    }

    public function updateMealReminders(Request $request, MealReminderBridge $bridge): RedirectResponse
    {
        $validated = $request->validate([
            'breakfast.enabled' => ['required', 'boolean'],
            'breakfast.time' => ['required', 'date_format:H:i'],
            'lunch.enabled' => ['required', 'boolean'],
            'lunch.time' => ['required', 'date_format:H:i'],
            'dinner.enabled' => ['required', 'boolean'],
            'dinner.time' => ['required', 'date_format:H:i'],
        ]);

        AppPreference::current()->update(['meal_reminders' => $validated]);

        $result = $bridge->sync($validated);
        $message = match ($result['status']) {
            'permission_requested' => 'Meal reminders saved. Allow notifications when prompted.',
            'notifications_disabled' => 'Meal reminders saved, but Android notifications are off.',
            'error' => 'Meal reminder settings saved, but reminders could not be scheduled.',
            default => null,
        };

        return $message ? back()->with('message', $message) : back();
    }
}
