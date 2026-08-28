<?php

namespace App\Services;

use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Models\WorkoutEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WeekSummaryService
{
    public const CALORIE_TOLERANCE = 50;

    public function __construct(private NutritionCalculator $calculator) {}

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
        $eatBack = AppPreference::current()->eatBack();
        $dayCount = (int) $start->diffInDays($end->copy()->startOfDay()) + 1;

        $days = collect(range(0, $dayCount - 1))
            ->map(function (int $offset) use ($start, $selectedDate, $entriesByDate, $workoutsByDate, $goal, $eatBack): array {
                $date = $start->copy()->addDays($offset);
                $dateString = $date->toDateString();
                $entry = $entriesByDate->get($dateString);
                $consumed = (int) ($entry?->calories ?? 0);
                $burned = (int) ($workoutsByDate->get($dateString)?->calories_burned ?? 0);
                $target = $goal ? $goal->calories + $this->calculator->eatenBackCalories($burned, $eatBack) : null;

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

        $logged = $days->filter(fn (array $day): bool => $day['consumed_calories'] > 0);

        return [
            'days' => $days->all(),
            'roundup' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'calories' => (int) $days->sum('consumed_calories'),
                'burned_calories' => (int) $days->sum('burned_calories'),
                'effective_target' => $goal ? (int) $days->sum('effective_target') : null,
                'average_calories' => $logged->isNotEmpty() ? (int) round($logged->avg('consumed_calories')) : null,
                'average_target' => $goal && $logged->isNotEmpty() ? (int) round($logged->avg('effective_target')) : null,
                'protein_g' => round((float) $days->sum('protein_g'), 2),
                'carbs_g' => round((float) $days->sum('carbs_g'), 2),
                'fat_g' => round((float) $days->sum('fat_g'), 2),
                'protein_goal_g' => $goal ? round((float) $goal->protein_g * $dayCount, 2) : null,
                'carbs_goal_g' => $goal ? round((float) $goal->carbs_g * $dayCount, 2) : null,
                'fat_goal_g' => $goal ? round((float) $goal->fat_g * $dayCount, 2) : null,
            ],
            'insights' => $this->insights($days, $start, $end, $goal),
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

    /**
     * @param  Collection<int, array{date: string, consumed_calories: int, effective_target: int|null, status: string}>  $days
     * @return list<array{id: string, text: string}>
     */
    private function insights(Collection $days, CarbonInterface $start, CarbonInterface $end, ?DailyGoal $goal): array
    {
        $insights = [];
        $logged = $days->filter(fn (array $day): bool => $day['consumed_calories'] > 0);

        if ($logged->isNotEmpty()) {
            $onTarget = $logged->where('status', 'target')->count();
            $insights[] = [
                'id' => 'adherence',
                'text' => "{$onTarget} of {$logged->count()} logged days on target",
            ];
        }

        if ($goal && $logged->isNotEmpty()) {
            $averageConsumed = (int) round($logged->avg('consumed_calories'));
            $averageTarget = (int) round($logged->avg('effective_target'));
            $insights[] = [
                'id' => 'average',
                'text' => 'Averaged '.number_format($averageConsumed).' kcal on logged days vs '.number_format($averageTarget).' target',
            ];
        }

        $weekdays = $logged->filter(fn (array $day): bool => Carbon::parse($day['date'])->isoWeekday() <= 5);
        $weekend = $logged->filter(fn (array $day): bool => Carbon::parse($day['date'])->isoWeekday() >= 6);

        if ($weekdays->isNotEmpty() && $weekend->isNotEmpty()) {
            $diff = (int) round($weekend->avg('consumed_calories') - $weekdays->avg('consumed_calories'));
            $insights[] = [
                'id' => 'weekend',
                'text' => match (true) {
                    $diff > 0 => 'Weekends averaged '.number_format($diff).' kcal more than weekdays',
                    $diff < 0 => 'Weekends averaged '.number_format(abs($diff)).' kcal less than weekdays',
                    default => 'Weekends averaged the same as weekdays',
                },
            ];
        }

        $weightInsight = $this->weightInsight($start, $end);

        if ($weightInsight !== null) {
            $insights[] = $weightInsight;
        }

        return $insights;
    }

    /**
     * @return array{id: string, text: string}|null
     */
    private function weightInsight(CarbonInterface $start, CarbonInterface $end): ?array
    {
        $metrics = BodyMetric::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('date')
            ->get();

        if ($metrics->count() < 2) {
            return null;
        }

        $deltaKg = round((float) $metrics->last()->weight_kg - (float) $metrics->first()->weight_kg, 1);
        $unit = AppPreference::current()->weight_unit === 'lb' ? 'lb' : 'kg';
        $display = $unit === 'lb'
            ? round(abs($deltaKg) * 2.2046226218, 1)
            : abs($deltaKg);

        $direction = match (true) {
            $deltaKg > 0 => 'up',
            $deltaKg < 0 => 'down',
            default => 'unchanged',
        };

        return [
            'id' => 'weight',
            'text' => $direction === 'unchanged'
                ? 'Weight unchanged this period'
                : "Weight {$direction} {$display} {$unit} this period",
        ];
    }
}
