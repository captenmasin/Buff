# 017 — Eat-back preference

- **Status**: TODO
- **Severity**: MEDIUM
- **Category**: Goals / honesty
- **Estimated scope**: `AppPreference` migration, `NutritionCalculator`, `DailySummaryService`, `WeekSummaryService`, `MacroController`, Settings UI, unit + feature tests
- **Depends on**: none (018 should run after this so weekly insights use the same effective target)

## Problem

Buff always adds workout calories onto the day’s food target:

```php
$effectiveCalories = $goal->calories + max($burnedCalories, 0);
```

in `NutritionCalculator::effectiveDailyGoal`. `DailySummaryService` remaining is `$calorieGoal + $burned - $consumed`. Weekly `effective_target` is `$goal->calories + $burned` per day. Macros scale up with that larger calorie number.

Wearables overestimate burn. People cutting who eat every “unlocked” calorie stall. There is no setting for **all / half / none**.

`CalorieRing.vue` still shows `{burned} burned` separately — keep that. The preference only changes **goal** and **remaining**, not the burned figure.

## Target

- Store `eat_back` on `app_preferences`: `all` | `half` | `none`. Default **`all`** so existing behavior and tests stay valid until the user changes it.
- `eatenBack = match { none => 0, half => round(burned/2), all => burned }`.
- Effective calorie goal = `goal.calories + eatenBack`. Macros scale with that, same ratio as today (`effectiveDailyGoal`).
- Remaining calories use the same eaten-back amount (not raw burned).
- Settings: a card **Exercise calories** with three options, saved immediately like units/reminders.
- Today ring: `goal` and `remaining` follow the preference; `burned` still shows full workout sum.

## Repo conventions to follow

- Migration: `php artisan make:migration add_eat_back_to_app_preferences_table --no-interaction`. String column default `'all'`, not JSON.
- `AppPreference::EAT_BACK = ['all', 'half', 'none']`. `$fillable` + `$attributes['eat_back' => 'all']`.
- `config/buff.php` `AppPreference` fields: append `'eat_back'`.
- Settings pattern: `PUT /settings/units` and `PUT /settings/meal-reminders` — add `PUT /settings/eat-back`. `SettingsController` validates `Rule::in(AppPreference::EAT_BACK)`, `back()` after save. Pass `preferences.eat_back` from `edit()`.
- Vue: `Settings.vue` card using existing `Button` / selected variant (see Weekly Week vs Range, or appearance options). `useForm` + put on change. Do not add a new radio primitive if `Button` variants suffice.
- Calculator stays the single source of truth. `DailySummaryService`, `WeekSummaryService`, `MacroController` must not reimplement half/none.
- Tests: extend `tests/Unit/NutritionCalculatorTest.php`, `tests/Feature/DashboardTest.php`, `tests/Feature/WeeklySummaryTest.php`, `tests/Feature/SettingsTest.php`. Default preference = all, so existing 2300 / 14300 assertions remain valid without extra setup.

## Steps

1. Migration:

```php
$table->string('eat_back', 8)->default('all');
```

2. `AppPreference`: constant, fillable, default attribute. Optional helper `eatBack(): string` that clamps unknown stored values to `all`.

3. `NutritionCalculator`:

```php
public function eatenBackCalories(int $burnedCalories, string $eatBack = 'all'): int
{
    $burned = max($burnedCalories, 0);

    return match ($eatBack) {
        'none' => 0,
        'half' => (int) round($burned / 2),
        default => $burned,
    };
}

public function effectiveDailyGoal(DailyGoal $goal, int $burnedCalories, string $eatBack = 'all'): array
```

Third argument defaults to `'all'` so the existing unit test `effectiveDailyGoal($goal, 300)` still expects 2300. Internally use `eatenBackCalories`.

4. `DailySummaryService::forDate`: `$eatBack = AppPreference::current()->eatBack()` (or `eat_back`). Pass into `effectiveDailyGoal`. Remaining:

```php
'calories_remaining' => $calorieGoal + $this->calculator->eatenBackCalories($burned, $eatBack) - $totals['calories'],
```

Do **not** use raw `$burned` in remaining.

5. `WeekSummaryService`: for each day, `effective_target` = `goal.calories + eatenBack(burned)` using the same preference. Roundup `effective_target` sum follows. Macro goals stay base grams × day count (today does **not** scale weekly protein goals by burn — leave that as-is). Day `status()` already uses `$target` — it will pick up eat-back automatically.

6. `MacroController`: pass eat-back into `effectiveDailyGoal` (it already loads burned).

7. Settings:

- `preferences.eat_back` in Inertia props (extend the existing `preferences` array).
- New card between Units and Meal reminders (or after Units): title **Exercise calories**, short copy: “How much of a workout to add to today’s food target.” Three buttons: Eat all back / Eat half back / Don’t eat back. Save `PUT /settings/eat-back` `{ eat_back }`.
- Route next to other settings puts.

8. Tests:

- Unit: burned 300 → all 300, half 150, none 0. `effectiveDailyGoal($goal, 300, 'half')` calories 2150; macros scaled 2150/2000. Odd burned 301 half → 151.
- Dashboard: preference `none`, same 2000 goal / 300 burn / 500 food → `summary.goal.calories` 2000, `calories_remaining` 1500, `summary.log.burned_calories` still 300. Preference `half` → goal 2150, remaining 1650.
- Weekly: `none` with the existing 7-day fixture (one 300 burn) → `roundup.effective_target` 14000 not 14300. `all` keeps 14300.
- Settings: put `eat_back=half` persists; invalid value errors; GET settings includes `preferences.eat_back`.

## Boundaries

- Do NOT hide workouts or stop Health Connect / Apple Health import.
- Do NOT change how `burned` is displayed on the ring.
- Do NOT add “eat back 25%” or a custom integer — three presets only.
- Do NOT persist per-day overrides.
- Do NOT scale weekly **macro goal totals** by eat-back unless you already do that daily (you do daily on Today; weekly protein_goal_g is base × days — leave weekly macros as they are).
- Do NOT add TDEE / adaptive goals.

## Verification

- **Mechanical**: `vendor/bin/pint --dirty --format agent`; `php artisan test --compact tests/Unit/NutritionCalculatorTest.php tests/Feature/DashboardTest.php tests/Feature/WeeklySummaryTest.php tests/Feature/MacroBreakdownTest.php tests/Feature/SettingsTest.php`.
- **Feel check**:
  - Goal 2000, log 400 burn, 0 food. **All**: 400 left of 2400. **Half**: 200 left of 2200. **None**: 2000 left of 2000. Burned line still says 400.
  - Toggle in Settings, return to Today: ring updates without logging again.
  - Week status dots follow the new target (a 2100-cal day with 0 burn is still over if goal is 2000).
- **Done when**: three presets persist, every effective-goal path uses them, default remains all, tests pass.
