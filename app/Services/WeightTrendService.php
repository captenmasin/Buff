<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class WeightTrendService
{
    private const PERIOD = 7;

    /**
     * @param  iterable<int, array{date: string, weight_kg: float|null}>  $chronological
     * @return list<array{date: string, weight_kg: float, trend_kg: float}>
     */
    public function forPoints(iterable $points): array
    {
        $alpha = 2 / (self::PERIOD + 1);
        $trended = [];
        $previousTrend = null;

        foreach ($points as $point) {
            if (! isset($point['weight_kg']) || $point['weight_kg'] === null) {
                continue;
            }

            $weight = (float) $point['weight_kg'];
            $trend = $previousTrend === null
                ? $weight
                : ($alpha * $weight) + ((1 - $alpha) * $previousTrend);

            $trend = round($trend, 2);
            $trended[] = [
                'date' => $point['date'],
                'weight_kg' => $weight,
                'trend_kg' => $trend,
            ];
            $previousTrend = $trend;
        }

        return $trended;
    }

    /**
     * @param  list<array{date: string, weight_kg: float, trend_kg: float}>  $trended
     */
    public function trendDelta(array $trended, string $asOfDate): ?float
    {
        if (count($trended) < 2) {
            return null;
        }

        $latest = $trended[array_key_last($trended)];
        $cutoff = Carbon::parse($asOfDate)->subDays(self::PERIOD)->toDateString();
        $baseline = null;

        foreach ($trended as $point) {
            if ($point['date'] <= $cutoff) {
                $baseline = $point;
            }
        }

        $baseline ??= $trended[0];

        return round($latest['trend_kg'] - $baseline['trend_kg'], 2);
    }
}
