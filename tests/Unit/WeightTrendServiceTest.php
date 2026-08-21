<?php

use App\Services\WeightTrendService;

it('computes an exponential moving average over weigh-ins', function (): void {
    $service = new WeightTrendService;

    expect($service->forPoints([
        ['date' => '2026-05-18', 'weight_kg' => 80],
        ['date' => '2026-05-19', 'weight_kg' => 81],
        ['date' => '2026-05-20', 'weight_kg' => 82],
    ]))->toBe([
        ['date' => '2026-05-18', 'weight_kg' => 80.0, 'trend_kg' => 80.0],
        ['date' => '2026-05-19', 'weight_kg' => 81.0, 'trend_kg' => 80.25],
        ['date' => '2026-05-20', 'weight_kg' => 82.0, 'trend_kg' => 80.69],
    ]);
});

it('returns a zero seven-day delta when the later weigh-in is unchanged', function (): void {
    $service = new WeightTrendService;
    $trended = $service->forPoints([
        ['date' => '2026-05-18', 'weight_kg' => 80],
        ['date' => '2026-05-25', 'weight_kg' => 80],
    ]);

    expect($service->trendDelta($trended, '2026-05-25'))->toBe(0.0);
});

it('returns a null delta for a single weigh-in', function (): void {
    $service = new WeightTrendService;
    $trended = $service->forPoints([
        ['date' => '2026-05-18', 'weight_kg' => 80],
    ]);

    expect($trended[0]['trend_kg'])->toBe(80.0)
        ->and($service->trendDelta($trended, '2026-05-18'))->toBeNull();
});
