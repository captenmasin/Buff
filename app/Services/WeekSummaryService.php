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

        return $this->forRange($start, $end, $selectedDate);
    }

    public function forRange(CarbonInterface $startDate, CarbonInterface $endDate, ?CarbonInterface $selectedDate = null): array
    {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        $entriesByDate = MealEntry::query()
            ->selectRaw('date, sum(calories) as calories, sum(protein_g) as protein_g, sum(carbs_g) as carbs_g, sum(fat_g) as fat_g')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->groupBy('date')
            ->get()
            ->keyBy(fn (MealEntry $entry): string => $entry->date->toDateString());

        $workoutsByDate = WorkoutEntry::query()
            ->selectRaw('date, sum(calories_burned) as calories_burned')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->groupBy('date')
            ->get()
            ->keyBy(fn (WorkoutEntry $workout): string => $workout->date->toDateString());

        $goal = DailyGoal::query()->latest('updated_at')->first();
        $dayCount = (int) $start->diffInDays($end->copy()->startOfDay()) + 1;

        $days = collect(range(0, $dayCount - 1))
            ->map(function (int $offset) use ($start, $selectedDate, $entriesByDate, $workoutsByDate, $goal): array {
                $date = $start->copy()->addDays($offset);
                $dateString = $date->toDateString();
                $entry = $entriesByDate->get($dateString);
                $consumed = (int) ($entry?->calories ?? 0);
                $burned = (int) ($workoutsByDate->get($dateString)?->calories_burned ?? 0);
                $target = $goal ? $goal->calories + $burned : null;

                return [
                    'date' => $dateString,
                    'label' => $date->isoFormat('dd')[0],
                    'is_selected' => $selectedDate !== null && $date->isSameDay($selectedDate),
                    'is_today' => $date->isToday(),
                    'consumed_calories' => $consumed,
                    'burned_calories' => $burned,
                    'effective_target' => $target,
                    'protein_g' => round((float) ($entry?->protein_g ?? 0), 2),
                    'carbs_g' => round((float) ($entry?->carbs_g ?? 0), 2),
                    'fat_g' => round((float) ($entry?->fat_g ?? 0), 2),
                    'status' => $this->status($goal !== null, $consumed, $target),
                ];
            })
            ->values();

        return [
            'days' => $days->all(),
            'roundup' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'calories' => (int) $days->sum('consumed_calories'),
                'burned_calories' => (int) $days->sum('burned_calories'),
                'effective_target' => $goal ? (int) $days->sum('effective_target') : null,
                'protein_g' => round((float) $days->sum('protein_g'), 2),
                'carbs_g' => round((float) $days->sum('carbs_g'), 2),
                'fat_g' => round((float) $days->sum('fat_g'), 2),
                'protein_goal_g' => $goal ? round((float) $goal->protein_g * $dayCount, 2) : null,
                'carbs_goal_g' => $goal ? round((float) $goal->carbs_g * $dayCount, 2) : null,
                'fat_goal_g' => $goal ? round((float) $goal->fat_g * $dayCount, 2) : null,
            ],
        ];
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
