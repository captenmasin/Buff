# 018 — Weekly insights

- **Status**: TODO
- **Severity**: MEDIUM
- **Category**: Progress loop
- **Estimated scope**: `WeekSummaryService`, `Weekly.vue`, `tests/Feature/WeeklySummaryTest.php` (optional unit test)
- **Depends on**: **017** recommended so “vs target” uses eat-back. If 017 is not done, insights still use today’s `effective_target` (eat all back).

## Problem

`/weekly` is a calorie total, macro bars, and a list of daily totals (`Weekly.vue` + `WeekSummaryService::forRange`). It does not say whether the week was adherent, whether weekends blew the average, or whether weight moved. The user has to eyeball seven rows.

`WeekSummaryService::status` already classifies each day (`target` / `under` / `over` / `neutral` with ±50 kcal). Reuse that; do not invent a second scoring system.

## Target

Add an `insights` array on the weekly payload. The page renders 2–4 short factual lines above Daily totals. No LLM. Omit an insight when its data is missing rather than showing placeholders.

Required insights (include only when they apply):

1. **Adherence** — among days with `consumed_calories > 0` (“logged days”): how many `status === 'target'`. Copy: `3 of 6 logged days on target`. If zero logged days, skip.
2. **Average on logged days** — `round(sum(consumed) / logged_days)` vs average effective target on those days. Copy: `Averaged 1,870 kcal on logged days vs 2,000 target`. Skip if no logged days or no goal.
3. **Weekend vs weekday** — mean consumed on logged weekdays (Mon–Fri) vs logged weekend days (Sat–Sun), using the date’s ISO day. Copy only if **both** groups have at least one logged day: `Weekends averaged 340 kcal more than weekdays` / `less than`. Skip if the range is a single side (e.g. Mon–Fri only with no weekend).
4. **Weight in range** — first and last `BodyMetric` with `date` inside `start_date`…`end_date` inclusive, at least two points: `Weight down 0.4 kg this period` / `up`. Use stored kg; Vue can convert with existing `bodyUnits` if you pass kg and `preferences.weight_unit`. **Simplest:** pass kg and a preformatted `text` from the server in the user’s current unit. Read `AppPreference::current()->weight_unit` and format with the same rounding as `formatBodyValue` (check `resources/js/bodyUnits.ts` — either duplicate a one-line round in PHP or pass kg and format in Vue). Prefer Vue formatting: pass `weight_delta_kg` plus the sentence key, or pass numbers and let Vue write the sentence. **Prefer one `text` string from PHP** so tests are stable.

Cap at these four. Do not add protein sermons, streaks calendars, or charts in this plan.

## Repo conventions to follow

- Compute in `WeekSummaryService::forRange` next to `roundup`, using the `$days` collection you already build. Do not query meals again.
- Body metrics: one extra query in `forRange` (`whereDate` between start and end, `orderBy date`). Fine at this scale.
- Weekly page already uses `Card` + `field-label`. Insights: a `Card` with a heading `Insights` and a stacked list (`divide-y` like Progress history). No new component unless it stays under `resources/js/Components`.
- Copy is full sentences, no emoji.
- Pest: extend `tests/Feature/WeeklySummaryTest.php`. Existing roundup assertions must remain.
- Units: if you format weight in PHP, `lb` values are `kg * 2.20462` rounded to 1 decimal to match `weightFromKg` in `bodyUnits.ts`. Read that helper and match it.

## Steps

1. In `WeekSummaryService::forRange`, after `$days` is built:

```php
'insights' => $this->insights($days, $start, $end, $goal),
```

Private method returns `list<array{id: string, text: string}>` with stable ids: `adherence`, `average`, `weekend`, `weight`.

2. Adherence / average / weekend: PHP over `$days` (already has `status`, `consumed_calories`, `effective_target`, `date`). Weekday: `Carbon::parse($date)->isoWeekday()` 1–5 vs 6–7.

3. Weight: `BodyMetric::query()->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->orderBy('date')->get()`. If count < 2, skip. Delta = last.weight_kg − first.weight_kg. Round to 1 decimal for display after unit conversion. “this period” not “this week” because mode can be a custom range.

4. `Weekly.vue`: new `insights` prop. If `insights.length`, render the card. Do not empty-state the card.

5. Tests (use the existing week of 2026-05-18…24 where possible):

- Fixture already has logged 18 (1800), 19 (2300), 20 (2100) with goal 2000 and 300 burn on the 19th. Assert `insights` contains adherence with the correct `X of Y logged days` (Y = 3). Status of those days already asserted (`week.1.status` target). Count target/under/over from the same rules.
- Average insight present when goal exists.
- Weekend vs weekday: add a Saturday and a Monday log with a large gap; assert the weekend sentence direction.
- Weight: two `BodyMetric`s inside the range → weight insight; metrics outside range ignored; one metric → no weight insight.
- Empty week (no meals): `insights` is `[]` or only omitted keys — `has('insights', 0)` if nothing applies.

6. If 017 is merged: add one test that `none` eat-back changes a day’s status and thus adherence text. If 017 is not merged, skip that test.

## Boundaries

- Do NOT call an LLM or add a copy/marketing layer.
- Do NOT add a new `/insights` route.
- Do NOT plot calories vs weight here (that is a later Progress overlay).
- Do NOT change `CALORIE_TOLERANCE` (50).
- Do NOT include `neutral` days in “logged days”.
- Do NOT show “0 of 0” or “No data” cards.
- Do NOT add protein/carb/fat insight lines in this plan.

## Verification

- **Mechanical**: `vendor/bin/pint --dirty --format agent`; `php artisan test --compact tests/Feature/WeeklySummaryTest.php`.
- **Feel check**:
  - A mixed week shows adherence + average. Custom range Mon–Tue with no weekend does **not** show the weekend line.
  - Two weigh-ins in range show weight up/down in the current unit.
  - Toggle 017 eat-back if present: a day that was “over” because of eat-all-back can become “on target” / “under” and the adherence sentence updates.
- **Done when**: weekly payload has deterministic insights, the page lists them without empty states, existing roundup tests still pass.
