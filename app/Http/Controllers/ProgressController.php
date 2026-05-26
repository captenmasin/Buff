<?php

namespace App\Http\Controllers;

use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\DailyGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function index(): Response
    {
        $metrics = BodyMetric::query()
            ->latest('date')
            ->limit(30)
            ->get();

        $latest = $metrics->first();
        $previous = $metrics->skip(1)->first();
        $goal = DailyGoal::query()->latest('updated_at')->first();
        $preferences = AppPreference::current();

        return Inertia::render('Progress', [
            'today' => today()->toDateString(),
            'preferences' => [
                'weight_unit' => $preferences->weight_unit,
                'height_unit' => $preferences->height_unit,
            ],
            'latest' => $latest ? $this->metricPayload($latest) : null,
            'goals' => $goal ? [
                'height_cm' => $goal->height_cm !== null ? (float) $goal->height_cm : null,
                'target_weight_kg' => $goal->target_weight_kg !== null ? (float) $goal->target_weight_kg : null,
                'target_body_fat_percent' => $goal->target_body_fat_percent !== null ? (float) $goal->target_body_fat_percent : null,
            ] : null,
            'delta' => $latest && $previous ? [
                'weight_kg' => round((float) $latest->weight_kg - (float) $previous->weight_kg, 2),
                'body_fat_percent' => $latest->body_fat_percent !== null && $previous->body_fat_percent !== null
                    ? round((float) $latest->body_fat_percent - (float) $previous->body_fat_percent, 2)
                    : null,
            ] : null,
            'history' => $metrics->map(fn (BodyMetric $metric): array => $this->metricPayload($metric))->all(),
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

        return back()->with('message', 'Progress updated.');
    }

    public function updateHeight(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'height_cm' => ['nullable', 'numeric', 'min:50', 'max:260'],
        ]);

        $goal = DailyGoal::query()->first();

        $goal
            ? $goal->update(['height_cm' => $validated['height_cm'] ?? null])
            : DailyGoal::query()->create([
                'calories' => 2000,
                'protein_g' => 170,
                'carbs_g' => 195,
                'fat_g' => 60,
                'macro_calories' => 2000,
                'height_cm' => $validated['height_cm'] ?? null,
            ]);

        return back()->with('message', 'Height updated.');
    }

    public function destroy(BodyMetric $bodyMetric): RedirectResponse
    {
        $bodyMetric->delete();

        return back()->with('message', 'Progress item removed.');
    }

    private function metricPayload(BodyMetric $metric): array
    {
        return [
            'id' => $metric->id,
            'date' => $metric->date->toDateString(),
            'weight_kg' => (float) $metric->weight_kg,
            'body_fat_percent' => $metric->body_fat_percent !== null ? (float) $metric->body_fat_percent : null,
            'notes' => $metric->notes,
        ];
    }
}
