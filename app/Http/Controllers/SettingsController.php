<?php

namespace App\Http\Controllers;

use App\BuffApiStatus;
use App\Models\AppPreference;
use App\Models\BodyProfile;
use App\Services\BuffApiClient;
use App\Services\BuffApiResult;
use App\Services\BuffCredentialStore;
use App\Services\MealReminderBridge;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function subscription(): Response
    {
        return Inertia::render('Settings/Subscription');
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

    public function connectedAssistants(BuffApiClient $api, BuffCredentialStore $credentials): Response|RedirectResponse
    {
        $result = $api->get('mcp/connections');

        if ($result->status === BuffApiStatus::Unauthenticated) {
            $credentials->clearToken();

            return redirect()->guest(route('account.login'));
        }

        return Inertia::render('Settings/ConnectedAssistants', [
            'connections' => $this->connections($result),
            'error' => $result->successful() ? null : $this->connectionError($result),
            'mcpEndpoint' => config('buff.mcp_url'),
        ]);
    }

    public function revokeConnectedAssistant(
        Request $request,
        BuffApiClient $api,
        BuffCredentialStore $credentials,
        string $connection,
    ): RedirectResponse {
        $result = $api->delete("mcp/connections/{$connection}");

        if ($result->status === BuffApiStatus::Unauthenticated) {
            $credentials->clearToken();
            $request->session()->put('url.intended', url('/settings/connected-assistants'));

            return redirect()->route('account.login');
        }

        if (! $result->successful()) {
            throw ValidationException::withMessages([
                'connection' => [$this->connectionError($result)],
            ]);
        }

        return back()->with('message', 'Assistant access revoked.');
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

        $result = $bridge->sync($validated);

        if ($result['status'] === 'error') {
            throw ValidationException::withMessages([
                'reminders' => ['Meal reminder settings were not saved because reminders could not be scheduled.'],
            ]);
        }

        AppPreference::current()->update(['meal_reminders' => $validated]);

        $message = match ($result['status']) {
            'permission_requested' => 'Meal reminders saved. Allow notifications when prompted.',
            'notifications_disabled' => 'Meal reminders saved, but notifications are off.',
            default => 'Meal reminders saved.',
        };

        return back()->with('save_status', $message);
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

    /**
     * @return array<int, array{id: string, clientName: string, linkedAt: ?string, lastUsedAt: ?string, revokedAt: ?string}>
     */
    private function connections(BuffApiResult $result): array
    {
        $connections = $result->data['data'] ?? null;

        if (! $result->successful() || ! is_array($connections)) {
            return [];
        }

        return collect($connections)
            ->map(function (mixed $connection): ?array {
                if (! is_array($connection)
                    || ! is_string($connection['id'] ?? null)
                    || ! Str::isUuid($connection['id'])
                    || ! is_string($connection['client_name'] ?? null)
                    || trim($connection['client_name']) === '') {
                    return null;
                }

                return [
                    'id' => $connection['id'],
                    'clientName' => trim($connection['client_name']),
                    'linkedAt' => is_string($connection['linked_at'] ?? null) ? $connection['linked_at'] : null,
                    'lastUsedAt' => is_string($connection['last_used_at'] ?? null) ? $connection['last_used_at'] : null,
                    'revokedAt' => is_string($connection['revoked_at'] ?? null) ? $connection['revoked_at'] : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function connectionError(BuffApiResult $result): string
    {
        return match ($result->status) {
            BuffApiStatus::ConnectionFailed => 'Could not connect to Buff. Check your connection and try again.',
            BuffApiStatus::Unauthenticated => 'Your session expired. Sign in again to manage assistants.',
            BuffApiStatus::RateLimited => 'Too many attempts. Try again shortly.',
            default => $result->message ?? 'Connected assistants could not be loaded. Try again.',
        };
    }
}
