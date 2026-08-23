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
        return Inertia::render('Settings', [
            'preferences' => $this->preferencesPayload(),
            'mealReminders' => AppPreference::current()->mealReminders(),
            'healthConnect' => HealthConnectController::sharedStatus(),
            'appleHealth' => AppleHealthController::sharedStatus(),
        ]);
    }

    public function account(): Response
    {
        return Inertia::render('Settings/Account', [
            'timezones' => timezone_identifiers_list(DateTimeZone::ALL),
        ]);
    }

    public function password(): Response
    {
        return Inertia::render('Settings/Password');
    }

    public function appearance(): Response
    {
        return Inertia::render('Settings/Appearance');
    }

    public function reminders(): Response
    {
        return Inertia::render('Settings/Reminders', [
            'mealReminders' => AppPreference::current()->mealReminders(),
        ]);
    }

    public function bodyProfile(): Response
    {
        return Inertia::render('Settings/BodyProfile', [
            'preferences' => $this->preferencesPayload(),
            'bodyProfile' => BodyProfile::current()->toPayload(),
        ]);
    }

    public function units(): Response
    {
        return Inertia::render('Settings/Units', [
            'preferences' => $this->preferencesPayload(),
        ]);
    }

    public function exercise(): Response
    {
        return Inertia::render('Settings/Exercise', [
            'preferences' => $this->preferencesPayload(),
        ]);
    }

    public function health(): Response|RedirectResponse
    {
        $healthConnect = HealthConnectController::sharedStatus();
        $appleHealth = AppleHealthController::sharedStatus();

        if ($healthConnect['is_android'] !== true && $appleHealth['is_ios'] !== true) {
            return redirect('/settings');
        }

        return Inertia::render('Settings/Health', [
            'healthConnect' => $healthConnect,
            'appleHealth' => $appleHealth,
        ]);
    }

    public function updateUnits(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'weight_unit' => ['required', Rule::in(AppPreference::WEIGHT_UNITS)],
            'height_unit' => ['required', Rule::in(AppPreference::HEIGHT_UNITS)],
            'measurement_unit' => ['required', Rule::in(AppPreference::MEASUREMENT_UNITS)],
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

    /**
     * @return array{weight_unit: string, height_unit: string, measurement_unit: string, eat_back: string}
     */
    private function preferencesPayload(): array
    {
        $preferences = AppPreference::current();

        return [
            'weight_unit' => $preferences->weight_unit,
            'height_unit' => $preferences->height_unit,
            'measurement_unit' => $preferences->measurement_unit,
            'eat_back' => $preferences->eatBack(),
        ];
    }
}
