<?php

namespace App\Http\Controllers;

use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\BodyProfile;
use App\Models\DailyGoal;
use App\Services\BodyMetricPhotoUploader;
use App\Services\EnergyEstimator;
use App\Services\WeightTrendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function index(Request $request, EnergyEstimator $estimator, WeightTrendService $trends): Response
    {
        $range = $request->string('range')->toString();
        $range = in_array($range, ['30', '90', '180', 'all'], true) ? $range : '90';

        $to = today()->startOfDay();
        $allMetrics = BodyMetric::query()->orderBy('date')->get();
        $from = $range === 'all'
            ? ($allMetrics->first()?->date?->copy()->startOfDay() ?? $to->copy())
            : $to->copy()->subDays(((int) $range) - 1);

        $trended = $trends->forPoints($allMetrics->map(fn (BodyMetric $metric): array => [
            'date' => $metric->date->toDateString(),
            'weight_kg' => (float) $metric->weight_kg,
        ]));
        $trendByDate = collect($trended)->keyBy('date');

        $windowMetrics = $allMetrics
            ->filter(fn (BodyMetric $metric): bool => $metric->date->greaterThanOrEqualTo($from)
                && $metric->date->lessThanOrEqualTo($to))
            ->sortByDesc(fn (BodyMetric $metric): int => $metric->date->timestamp)
            ->values();

        $latest = $allMetrics->last();
        $previous = $allMetrics->count() >= 2 ? $allMetrics[$allMetrics->count() - 2] : null;
        $latestTrend = $trended === [] ? null : $trended[array_key_last($trended)]['trend_kg'];
        $goal = DailyGoal::query()->latest('updated_at')->first();
        $profile = BodyProfile::current();
        $preferences = AppPreference::current();
        $targetWeight = $goal?->target_weight_kg !== null ? (float) $goal->target_weight_kg : null;

        return Inertia::render('Progress', [
            'today' => today()->toDateString(),
            'range' => $range,
            'range_start' => $from->toDateString(),
            'range_end' => $to->toDateString(),
            'preferences' => [
                'weight_unit' => $preferences->weight_unit,
                'height_unit' => $preferences->height_unit,
            ],
            'latest' => $latest ? $this->metricPayload($latest, $this->trendKg($trendByDate, $latest)) : null,
            'profile' => $profile->toPayload(),
            'goals' => $goal ? [
                'target_weight_kg' => $targetWeight,
                'target_body_fat_percent' => $goal->target_body_fat_percent !== null ? (float) $goal->target_body_fat_percent : null,
            ] : null,
            'energy' => $latest ? $estimator->estimate(
                $latest->weight_kg,
                $profile->height_cm,
                $profile->age,
                $profile->sex,
                $profile->activity_level,
            ) : null,
            'trend' => $latestTrend === null ? null : [
                'weight_kg' => $latestTrend,
                'delta_kg' => $trends->trendDelta($trended, $latest?->date->toDateString() ?? $to->toDateString()),
            ],
            'delta' => $latest && $previous ? [
                'weight_kg' => round((float) $latest->weight_kg - (float) $previous->weight_kg, 2),
                'body_fat_percent' => $latest->body_fat_percent !== null && $previous->body_fat_percent !== null
                    ? round((float) $latest->body_fat_percent - (float) $previous->body_fat_percent, 2)
                    : null,
            ] : null,
            'history' => $windowMetrics
                ->map(fn (BodyMetric $metric): array => $this->metricPayload($metric, $this->trendKg($trendByDate, $metric)))
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'weight_kg' => ['required', 'numeric', 'min:1', 'max:1000'],
            'body_fat_percent' => ['nullable', 'numeric', 'min:1', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        BodyMetric::query()->updateOrCreate(
            ['date' => Carbon::parse($validated['date'])->startOfDay()],
            [
                'weight_kg' => $validated['weight_kg'],
                'body_fat_percent' => $validated['body_fat_percent'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return $this->redirectToProgress($request)->with('message', 'Progress updated.');
    }

    public function destroy(Request $request, BodyMetric $bodyMetric, BodyMetricPhotoUploader $photos): RedirectResponse
    {
        $photos->discardForMetric($bodyMetric->id);
        $bodyMetric->delete();

        return $this->redirectToProgress($request)->with('message', 'Progress item removed.');
    }

    /**
     * @param  Collection<string, array{date: string, weight_kg: float, trend_kg: float}>  $trendByDate
     */
    private function trendKg(Collection $trendByDate, BodyMetric $metric): ?float
    {
        $point = $trendByDate->get($metric->date->toDateString());

        return is_array($point) ? $point['trend_kg'] : null;
    }

    private function redirectToProgress(Request $request): RedirectResponse
    {
        return redirect('/progress?range='.$this->progressRange($request));
    }

    private function progressRange(Request $request): string
    {
        $candidates = [
            $request->query('range'),
            $request->input('range'),
        ];

        $referer = $request->headers->get('referer');

        if (is_string($referer)) {
            parse_str((string) parse_url($referer, PHP_URL_QUERY), $query);
            $candidates[] = $query['range'] ?? null;
        }

        foreach ($candidates as $range) {
            if (is_string($range) && in_array($range, ['30', '90', '180', 'all'], true)) {
                return $range;
            }
        }

        return '90';
    }

    /**
     * @return array{id: string, date: string, weight_kg: float, body_fat_percent: float|null, notes: string|null, trend_kg: float|null}
     */
    private function metricPayload(BodyMetric $metric, ?float $trendKg = null): array
    {
        return [
            'id' => $metric->id,
            'date' => $metric->date->toDateString(),
            'weight_kg' => (float) $metric->weight_kg,
            'body_fat_percent' => $metric->body_fat_percent !== null ? (float) $metric->body_fat_percent : null,
            'notes' => $metric->notes,
            'trend_kg' => $trendKg,
        ];
    }
}
