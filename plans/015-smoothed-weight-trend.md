# 015 — Smoothed weight trend

- **Status**: TODO
- **Severity**: HIGH
- **Category**: Progress loop
- **Estimated scope**: new `WeightTrendService`, `ProgressController`, `Progress.vue`, unit + feature tests
- **Depends on**: **014** (date-true X axis and range windows). If 014 is skipped, copy its X-by-date `chartPoints` into this plan before drawing a second polyline.

## Problem

The Progress headline delta is last weigh-in minus the previous one (`ProgressController` `delta.weight_kg`). Day-to-day scale noise looks like fat loss or gain. The chart only plots raw points. There is no 7-day-style trend, no distance to goal, and (after 013) starting weight is just another raw dot.

## Target

- Compute a **trend weight** with an exponential moving average over chronological weigh-ins.
- Formula, in kg, oldest → newest:

```
N = 7
alpha = 2 / (N + 1)   // 0.25
trend[0] = weight[0]
trend[i] = alpha * weight[i] + (1 - alpha) * trend[i - 1]
```

- Run EMA over **all** metrics in time order, then slice to the visible chart window so the line does not reset at the range boundary.
- Headline Weight card shows **trend** and **trend change vs ~7 days ago**, not raw last-minus-previous.
- Chart: keep the raw polyline; add a second polyline for `trend_kg` (use `stroke="var(--muted-foreground)"` or existing `--primary` at lower visual weight — raw stays `--primary`, trend uses `stroke-muted-foreground` / `var(--color-muted-foreground)`). Raw can stay markers-as-line; trend is the smoother line.
- If a target weight exists, show distance: `trend - target` with the same up/down coloring as today’s raw delta (`text-destructive` if trend is above target when that implies the wrong direction? **Simpler rule:** match current Progress coloring: positive kg change is `text-destructive`, negative is `text-success-foreground`. Distance to goal: show `X kg to go` as `abs(trend - target)` and a short “above goal” / “below goal” label. Do not color distance as success unless trend is on the goal side.)
- Body fat stays raw in this plan.

## Repo conventions to follow

- New service: `php artisan make:class Services/WeightTrendService --no-interaction`. Mirror `NutritionCalculator` (plain class, no facade).
- Unit test: `php artisan make:test --pest --unit WeightTrendServiceTest --no-interaction`.
- Feature assertions on `/progress` props in `tests/Feature/ProgressTest.php`.
- Payload stays kg; Vue converts for display.
- Do not add npm dependencies.

## Steps

1. Implement `App\Services\WeightTrendService`:

```php
/**
 * @param  iterable<int, array{date: string, weight_kg: float}>  $chronological
 * @return list<array{date: string, weight_kg: float, trend_kg: float}>
 */
public function forPoints(iterable $points): array
```

Accept already-sorted oldest-first points. Ignore null weights. Use `round($trend, 2)` like other body numbers.

Add `trendDelta(array $trended, string $asOfDate): ?float`: latest trend minus the trend of the last point with `date <= asOfDate - 7 days`. If none, minus the earliest trend. If fewer than 2 points, return null.

Add `distanceToGoal(?float $trendKg, ?float $targetKg): ?float` → `round($trendKg - $targetKg, 2)` or null.

2. `ProgressController::index`: load **all** metrics oldest-first for the service (cheap on-device). Build trended list. Filter to the 014 window for `history` / chart. Pass:

```php
'trend' => $latestTrend === null ? null : [
    'weight_kg' => $latestTrend,
    'delta_kg' => $delta, // nullable
    'to_goal_kg' => $toGoal, // nullable
],
```

Each `history` item gains `trend_kg` (null only if that date was not in the EMA input — it always is if it has weight).

Keep raw `latest` / `delta` in the payload for one release if tests depend on `delta.weight_kg`, but **UI must not use raw delta for the Weight card**. Update `ProgressTest` `where('delta.weight_kg', -0.6)` to also assert `trend` (and you may keep `delta` for compatibility). Prefer updating the test to the new headline contract and drop UI use of `delta`. If nothing else reads `delta`, remove it from the page props in this plan to avoid two competing numbers.

3. `Progress.vue`:

- Weight card primary number: trend in display units (fallback to latest raw if only one point — trend equals raw).
- Subline: trend delta over 7 days via `deltaLabel`, not `props.delta.weight_kg`.
- Optional third line: distance to goal when `goals.target_weight_kg` is set.
- Chart: `trendPoints` polyline behind or on top of raw (trend on top). Same `xForDate` from 014.

4. Unit tests (fixed numbers):

- Weights 80, 81, 82 on consecutive days → `trend[0] === 80`, `trend[1] === 0.25*81 + 0.75*80 === 80.25`, `trend[2] === 0.25*82 + 0.75*80.25 === 80.69`.
- 7-day delta: points on day 0 and day 7 only, equal weights → delta 0.
- Single point → delta null, trend equals weight.

5. Feature: two metrics 83 then 82.4 (existing test). Assert `trend.weight_kg` is the EMA of those two (not 82.4 raw delta −0.6 as the headline). Compute expected: chronological 83 then 82.4 → trend 83, then `0.25*82.4 + 0.75*83 = 82.85`. `trend.delta_kg` may be null if dates are consecutive (< 7 days) — then delta is vs earliest trend: `82.85 - 83 = -0.15`. Document that in the test.

## Boundaries

- Do NOT smooth body fat.
- Do NOT auto-adjust calorie goals from trend (no adaptive TDEE).
- Do NOT invent missing daily weights for the EMA. EMA steps only on actual weigh-ins. The line is still date-true: X is the weigh-in date (014). Do not draw a daily interpolated curve unless it comes for free from connecting those dated trend points (polyline through weigh-in dates is enough).
- Do NOT change 014 range pills.
- Do NOT add a second chart library.

## Verification

- **Mechanical**: `vendor/bin/pint --dirty --format agent`; `php artisan test --compact tests/Unit/WeightTrendServiceTest.php tests/Feature/ProgressTest.php`.
- **Feel check**:
  - Alternate +1 / −1 kg daily for two weeks. Headline barely moves; raw polyline zigzags; trend line is calmer.
  - Set a target below trend: “to go” is positive kg to lose. Reverse if target is above (gain).
  - Range 30 vs All: trend at the right edge matches (EMA not reset).
- **Done when**: Weight card is trend-based, chart has a trend polyline on a date axis, unit+feature tests pass.
