<?php

namespace App\Http\Controllers;

use App\Models\AppPreference;
use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Services\DailySummaryService;
use App\Services\MealReminderBridge;
use App\Services\MealSuggestions;
use App\Services\WeekSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DailySummaryService $summary, WeekSummaryService $weekSummary, MealSuggestions $suggestions): Response|RedirectResponse
    {
        if (DailyGoal::query()->doesntExist() && ! $request->boolean('skip_onboarding')) {
            return redirect('/onboarding');
        }

        if ($request->query('add') === '1') {
            return redirect('/add'.($request->filled('date') ? '?date='.$request->string('date')->toString() : ''));
        }

        $validated = $request->validate(['date' => ['nullable', 'date']]);
        $date = isset($validated['date'])
            ? Carbon::parse($validated['date'])
            : today();

        $daily = $summary->forDate($date);
        $missingMeals = array_values(array_filter(MealEntry::MEAL_TYPES, fn (string $meal): bool => empty($daily['entries'][$meal])));

        if ($date->isToday()) {
            defer(fn () => app(MealReminderBridge::class)->refreshOnIos(), 'refresh-meal-reminders');
        }

        return Inertia::render('Today', [
            'summary' => $daily,
            'week' => $weekSummary->forDate($date)['days'],
            'mealTypes' => MealEntry::MEAL_TYPES,
            'mealSuggestions' => fn (): array => $date->isToday() ? $suggestions->forMissingMeals($date, $missingMeals) : [],
            'weeklyMealObservation' => fn (): ?array => $date->isToday() ? $suggestions->weeklyObservation($date) : null,
            'offerMealReminder' => fn (): bool => $request->session()->get('offer_meal_reminder') === true
                && ! in_array(true, array_column(AppPreference::current()->mealReminders(), 'enabled'), true),
            'subscriptionPromptEligible' => MealEntry::query()
                ->where('date', '<', today()->addDay()->toDateString())
                ->distinct()
                ->count('date') >= 3,
            'healthConnect' => HealthConnectController::sharedStatus(),
            'appleHealth' => AppleHealthController::sharedStatus(),
        ]);
    }
}
