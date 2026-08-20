# 014 — Date-true progress charts and longer history

- **Status**: TODO
- **Severity**: HIGH
- **Category**: Progress loop
- **Estimated scope**: `ProgressController`, `Progress.vue`, `tests/Feature/ProgressTest.php`
- **Depends on**: none (013 only adds the first point; charts work with any metrics)

## Problem

`ProgressController::index` loads `BodyMetric::latest('date')->limit(30)`. The Vue chart then spaces those points evenly by **index**:

```ts
`${(index / Math.max(values.length - 1, 1)) * 100},${...}`
```

in `resources/js/Pages/Progress.vue` `chartPoints`. Two weigh-ins a week apart sit as far apart as two consecutive days. A 31st measurement disappears. That is a sparkline of samples, not a progress chart.

## Target

- Chart X is calendar time inside a selected window.
- Windows: **30 / 90 / 180 / all** days. Default **90**. Query `?range=90` (and `30`, `180`, `all`).
- Load every metric in that window (no 30 cap). `all` = every metric, axis from first date through today.
- Raw weight and body-fat polylines plot only on days that have a value. Gaps are **not** filled with zero or interpolated in this plan (015 adds a trend line).
- A single point sits at its date’s X, not at 50%.
- History list below the chart shows the **same window**, newest first.
- Range control is a compact pill row on Progress (same `Button` variant pattern as Weekly’s Week/Range toggle). Changing range is an Inertia visit preserving scroll.

## Repo conventions to follow

- Range validation on the controller, not in Vue-only state that can drift from the payload.
- Weights in the payload stay kg; Vue still converts with `weightFromKg` for display and Y scaling.
- Keep the existing SVG `viewBox="0 0 100 100"` approach. Do not add Chart.js / D3 / a new dependency.
- Target weight / target body fat dashed lines stay (`stroke="var(--food)"` as today).
- Pest feature tests in `tests/Feature/ProgressTest.php`. Existing `has('history', 2)` must still pass on the default range.
- `php artisan make:` is not needed unless you extract a tiny helper class — prefer controller + Vue for this plan.

## Steps

1. In `ProgressController::index`, read range:

```php
$range = $request->string('range')->toString();
$range = in_array($range, ['30', '90', '180', 'all'], true) ? $range : '90';
```

Inject `Request $request`. Compute `$from` / `$to`:

- `all`: `$from` = earliest metric date or today if none; `$to` = today
- otherwise: `$to` = today, `$from` = today minus (N − 1) days

2. Replace `limit(30)` with:

```php
$metrics = BodyMetric::query()
    ->when($range !== 'all', fn ($q) => $q->whereDate('date', '>=', $from))
    ->whereDate('date', '<=', $to)
    ->orderByDesc('date')
    ->get();
```

`latest` / `previous` / `delta` (last two **in this window**) can stay as today for this plan — 015 will replace the headline delta with trend. Do not change delta semantics here except that they come from the unfiltered latest two **overall**, not the window. **Important**: headline latest/delta must remain **global** latest two measurements, not “latest in the 30-day window”. Implement as:

```php
$latest = BodyMetric::query()->latest('date')->first();
$previous = BodyMetric::query()->latest('date')->skip(1)->first();
```

Window `$metrics` is only for `history` + chart.

3. Pass extra props:

```php
'range' => $range,
'range_start' => $from->toDateString(),
'range_end' => $to->toDateString(),
```

`history` remains the window metrics mapped through `metricPayload`.

4. In `Progress.vue`, add range pills that `router.visit(\`/progress?range=${key}\`, { preserveScroll: true })`. Highlight the active range from `props.range`.

5. Replace `chartPoints` so X uses dates:

```ts
function xForDate(date: string): number {
    const start = Date.parse(props.range_start);
    const end = Date.parse(props.range_end);
    const span = Math.max(end - start, 1);
    return ((Date.parse(date) - start) / span) * 100;
}
```

Build polyline points as `${xForDate(metric.date)},${y}` for non-null Y values, in **chronological** order (already have `chartMetrics` reversed from history). Empty history: do not render a polyline (existing `v-if="hasHistory"` already wraps the charts).

6. Y scaling (`rangeFor`) still uses displayed weights in the window plus target. Unchanged idea.

7. Tests:

- Keep `it('renders latest metric delta and history')` — two points still `has('history', 2)` and default `range` is `'90'`.
- Add metrics at `today`, `today-3`, `today-40`, `today-100`. `GET /progress` (90) includes the first three, not the 100-day-old one. `GET /progress?range=30` includes only today and today-3. `GET /progress?range=all` includes all four.
- Invalid `?range=7` falls back to `90`.
- Global latest is still the newest metric even when viewing `range=30` that excludes it? Wait — if latest is today it is always in 30/90/180. Add a case: freeze today, latest is today; older points filtered by range. That’s enough.
- Do **not** assert SVG point strings in PHP. Optionally add a tiny function in `resources/js/weightChart.ts` and skip JS unit tests unless the repo already has them (it does not — keep math in the Vue file).

## Boundaries

- Do NOT add EMA / smoothed line (015).
- Do NOT add calorie-vs-weight overlay.
- Do NOT paginate history with a new page; window filter is the pagination.
- Do NOT change onboarding (013).
- Do NOT pull health-app weights.
- Do NOT introduce a chart library.

## Verification

- **Mechanical**: `vendor/bin/pint --dirty --format agent`; `php artisan test --compact tests/Feature/ProgressTest.php`.
- **Feel check**:
  - Log weight today and 10 days ago only. On 90-day range the two dots are near the right edge, ~10% of the axis apart, not at 0% and 100%.
  - Switch 30 / 90 / 180 / All; older points appear/disappear; no full-page jump (`preserveScroll`).
  - One weigh-in: a single dot at the correct date X, not stretched into a line.
  - Target dashed line still draws when a target weight is set.
- **Done when**: X is calendar-based, default window is 90 days, history matches the window, tests pass.
