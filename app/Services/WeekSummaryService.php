<?php

namespace App\Services;

use App\Models\DailyGoal;
use App\Models\DailyLog;
use App\Models\MealEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

        $logsByDate = DailyLog::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (DailyLog $log): string => $log->date->toDateString());

        $goals = DailyGoal::query()
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->oldest('starts_on')
            ->get();

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $selectedDate, $entriesByDate, $logsByDate, $goals): array {
                $date = $start->copy()->addDays($offset);
                $dateString = $date->toDateString();
                $goal = $this->goalForDate($goals, $date);
                $consumed = (int) ($entriesByDate->get($dateString)?->calories ?? 0);
                $burned = (int) ($logsByDate->get($dateString)?->burned_calories ?? 0);
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

    private function goalForDate(Collection $goals, CarbonInterface $date): ?DailyGoal
    {
        return $goals
            ->filter(fn (DailyGoal $goal): bool => $goal->starts_on->lte($date))
            ->last();
    }
}
