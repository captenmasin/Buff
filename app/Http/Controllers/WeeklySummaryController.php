<?php

namespace App\Http\Controllers;

use App\Services\WeekSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WeeklySummaryController extends Controller
{
    public function __invoke(Request $request, WeekSummaryService $weekSummary): Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'required_with:start_date', 'after_or_equal:start_date'],
        ]);

        $date = isset($validated['date'])
            ? Carbon::parse($validated['date'])
            : today();

        $mode = 'week';

        if (isset($validated['start_date'], $validated['end_date'])) {
            $mode = 'range';
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);

            if ($startDate->diffInDays($endDate) >= 90) {
                throw ValidationException::withMessages([
                    'end_date' => 'Choose a range of 90 days or less.',
                ]);
            }

            $summary = $weekSummary->forRange($startDate, $endDate);
        } else {
            $summary = $weekSummary->forDate($date);
            $startDate = Carbon::parse($summary['roundup']['start_date']);
            $endDate = Carbon::parse($summary['roundup']['end_date']);
        }

        return Inertia::render('Weekly', [
            'mode' => $mode,
            'selectedDate' => $date->toDateString(),
            'controls' => [
                'date' => $date->toDateString(),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'week' => $summary['days'],
            'roundup' => $summary['roundup'],
            'insights' => $summary['insights'],
        ]);
    }
}
