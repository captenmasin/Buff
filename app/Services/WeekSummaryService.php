<?php

namespace App\Services;

use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Models\WorkoutEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class WeekSummaryService
{
    public const CALORIE_TOLERANCE = 50;

    public function forDate(CarbonInterface $selectedDate): array
    {
        $start = $selectedDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        $entriesByDate = MealEntry::query()
            ->selectRaw('date, sum(calories) as calories')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('date')
            ->get()
            ->keyBy(fn (MealEntry $entry): string => $entry->date->toDateString());

        $workoutsByDate = WorkoutEntry::query()
            ->selectRaw('date, sum(calories_burned) as calories_burned')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('date')
            ->get()
            ->keyBy(fn (WorkoutEntry $workout): string => $workout->date->toDateString());

        $goal = DailyGoal::query()->latest('updated_at')->first();

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $selectedDate, $entriesByDate, $workoutsByDate, $goal): array {
                $date = $start->copy()->addDays($offset);
                $dateString = $date->toDateString();
                $consumed = (int) ($entriesByDate->get($dateString)?->calories ?? 0);
                $burned = (int) ($workoutsByDate->get($dateString)?->calories_burned ?? 0);
                $target = $goal ? $goal->calories + $burned : null;

                return [
                    'date' => $dateString,
                    'label' => $date->isoFormat('dd')[0],
                    'is_selected' => $date->isSameDay($selectedDate),
                    'is_today' => $date->isToday(),
                    'consumed_calories' => $consumed,
                    'effective_target' => $target,
                    'status' => $this->status($goal !== null, $consumed, $target),
                ];
            })
            ->all();
    }

    public function status(bool $hasGoal, int $consumed, ?int $target): string
    {
        if (! $hasGoal || $consumed === 0 || $target === null) {
            return 'neutral';
        }

        if (abs($consumed - $target) <= self::CALORIE_TOLERANCE) {
            return 'target';
        }

        return $consumed < $target ? 'under' : 'over';
    }
}
