# 013 — Capture starting weight on day one

- **Status**: TODO
- **Severity**: HIGH
- **Category**: Progress loop
- **Estimated scope**: `OnboardingController`, `Onboarding.vue`, `tests/Feature/OnboardingTest.php` (optional small Progress assertion)

## Problem

Onboarding’s Body & Units step asks for **target** weight, not current weight. The field is even labelled `Target {{ form.weight_unit }}` and bound to `target_weight_kg` via `weightDisplay` in `resources/js/Pages/Onboarding.vue`. Finishing onboarding creates a `DailyGoal` and units, but never a `BodyMetric`. Progress therefore starts empty until the user remembers to log a measurement later.

A progress app needs a start line on day one. Target without current weight cannot show distance-to-goal or a trend origin.

`OnboardingController::store` today (`app/Http/Controllers/OnboardingController.php`):

- Validates optional `target_weight_kg`, `height_cm`, `target_body_fat_percent`
- `DailyGoal::firstOrCreate` with those body fields
- Updates `AppPreference` units
- Does **not** write `body_metrics`

`ProgressController::store` already `updateOrCreate`s one metric per date. Reuse that shape; do not invent a second weight store.

## Target

- Onboarding Body & Units collects **current weight** (required) and **target weight** (optional, already exists).
- Submitting onboarding creates a `BodyMetric` for `today()` with that current weight (kg), `body_fat_percent` null, `notes` null.
- Height and target body fat stay optional.
- Existing users who already have a `DailyGoal` are unchanged (onboarding is skipped). Do not backfill fake weights.
- Units still convert through `resources/js/bodyUnits.ts` the same way Progress does (`weightToKg` / `weightFromKg`).

## Repo conventions to follow

- Controllers validate inline with array rules (no Form Request classes in this app).
- Weight is stored in kg. Display uses `AppPreference` units. See `Onboarding.vue` `syncStoredFromDisplay` and `Progress.vue` `saveMetric`.
- `BodyMetric` is a `SyncedModel` (UUID, microsecond timestamps). Creating one during onboarding will enqueue sync via existing model hooks — do not special-case sync.
- One metric per date: `BodyMetric::query()->updateOrCreate(['date' => today()->startOfDay()], ...)`.
- Feature tests: Pest `it(...)` in `tests/Feature/OnboardingTest.php`. `beforeEach` already authenticates via `EnsureBuffAccount`.
- Generate nothing with a second stack. No TDEE, no goal-type, no weekly rate in this plan.

## Steps

1. In `OnboardingController::create` defaults, add `'current_weight_kg' => null`. Keep `target_weight_kg`.

2. In `OnboardingController::store` validation, add:

```php
'current_weight_kg' => ['required', 'numeric', 'min:1', 'max:1000'],
```

Keep `target_weight_kg` nullable as today.

3. After the goal `firstOrCreate` and preference update, write the starting measurement:

```php
BodyMetric::query()->updateOrCreate(
    ['date' => today()->startOfDay()],
    [
        'weight_kg' => $validated['current_weight_kg'],
        'body_fat_percent' => null,
        'notes' => null,
    ]
);
```

Import `App\Models\BodyMetric`. Do not copy target body fat onto this first weigh-in.

4. In `resources/js/Pages/Onboarding.vue`:

- Add `currentWeightDisplay` ref and keep `weightDisplay` for **target** only (or rename target to `targetWeightDisplay` — pick one name and use it consistently).
- Extend `useForm` with `current_weight_kg: props.defaults.current_weight_kg ?? ''`.
- Update `syncDisplayFromStored` / `syncStoredFromDisplay` so current and target convert independently through `weightFromKg` / `weightToKg`.
- In the Body profile card, add a **Current {{ form.weight_unit }}** field **above** target. Label target `Target {{ form.weight_unit }}` (already). Show `form.errors.current_weight_kg`.
- Disable Start when current weight is empty (in addition to existing processing). Current weight is required; target is not.
- Do not add a third onboarding step.

5. Tests in `tests/Feature/OnboardingTest.php`:

- Update `it('stores the initial profile and preferences')` to post `current_weight_kg` (e.g. `90`) and assert a `body_metrics` row for today with that kg value. Keep the existing `target_weight_kg => 82` assertion.
- Add `it('requires current weight during onboarding')` posting the existing payload **without** `current_weight_kg` → `assertSessionHasErrors('current_weight_kg')` and `assertDatabaseCount('body_metrics', 0)`.
- Add `it('does not require a target weight')` with current weight, no target → redirect `/`, metric exists, `daily_goals.target_weight_kg` null.

Use `Carbon::setTestNow` / `Date::setTestNow` only if today would make the metric date flaky; otherwise `today()` in the assertion is fine (`->assertDatabaseHas('body_metrics', ['weight_kg' => 90])` plus count 1).

6. Optional: in `tests/Feature/ProgressTest.php`, one test that after onboarding-style metric create, `/progress` has `latest.weight_kg`. Skip if OnboardingTest already proves the row exists — do not duplicate unless useful.

## Boundaries

- Do NOT add age, sex, activity, TDEE, or lose/maintain/gain.
- Do NOT change Goals.vue calorie entry.
- Do NOT import Apple Health / Health Connect weight.
- Do NOT change Progress chart math (014 / 015).
- Do NOT set `notes` to marketing copy like "Starting weight".
- Do NOT create a metric when `skip_onboarding` is used on `/`.
- Do NOT modify existing users’ data.

## Verification

- **Mechanical**: `vendor/bin/pint --dirty --format agent`; `php artisan test --compact tests/Feature/OnboardingTest.php`. Expect pass.
- **Feel check**:
  - Fresh account: Body step shows Current and Target. Start disabled until Current is filled. After Start, Progress is not the empty state — latest weight is the current value just entered. Target line appears only if Target was filled.
  - Units: set lb, enter current `198.4`, stored kg is ~90.
  - Returning user with goals: `/onboarding` still redirects home.
- **Done when**: onboarding requires current weight, writes one `BodyMetric` for today, target remains optional, tests pass.
