<?php

namespace App\Http\Controllers;

use App\Models\BodyMetric;
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

        return Inertia::render('Progress', [
            'today' => today()->toDateString(),
            'latest' => $latest ? $this->metricPayload($latest) : null,
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
