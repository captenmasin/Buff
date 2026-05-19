<?php

namespace App\Http\Controllers;

use App\Models\MealEntry;
use App\Services\DailySummaryService;
use App\Services\WeekSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DailySummaryService $summary, WeekSummaryService $weekSummary): Response
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : today();

        return Inertia::render('Today', [
            'summary' => $summary->forDate($date),
            'week' => $weekSummary->forDate($date),
            'mealTypes' => MealEntry::MEAL_TYPES,
        ]);
    }

    public function updateBurnedCalories(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'burned_calories' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        \App\Models\DailyLog::query()->updateOrCreate(
            ['date' => Carbon::parse($validated['date'])->startOfDay()],
            ['burned_calories' => $validated['burned_calories']]
        );

        return back()->with('message', 'Burned calories updated.');
    }
}
