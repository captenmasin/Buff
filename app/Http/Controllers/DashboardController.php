<?php

namespace App\Http\Controllers;

use App\Models\MealEntry;
use App\Services\DailySummaryService;
use App\Services\WeekSummaryService;
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
            'week' => $weekSummary->forDate($date)['days'],
            'mealTypes' => MealEntry::MEAL_TYPES,
            'healthConnect' => HealthConnectController::sharedStatus(),
        ]);
    }
}
