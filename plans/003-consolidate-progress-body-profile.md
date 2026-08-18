# Plan 003: Make Progress the home for body tracking

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving on. If a STOP condition occurs, stop and report; do not improvise. When done, update this plan's row in `plans/README.md` unless a reviewer owns the index.
>
> **Drift check (run first)**: Run both `git diff --stat 21884fc -- routes/web.php app/Http/Controllers/ProgressController.php app/Http/Controllers/SettingsController.php resources/js/Pages/Progress.vue resources/js/Pages/Settings.vue tests/Feature/ProgressTest.php tests/Feature/SettingsTest.php` and `git status --short --untracked-files=all -- routes/web.php app/Http/Controllers/ProgressController.php app/Http/Controllers/SettingsController.php resources/js/Pages/Progress.vue resources/js/Pages/Settings.vue tests/Feature/ProgressTest.php tests/Feature/SettingsTest.php`.
> If an in-scope file changed, compare the excerpts below with live code. Any behavioral mismatch is a STOP condition.

## Status

- **Priority**: P1
- **Effort**: L
- **Risk**: MED
- **Depends on**: none
- **Category**: direction
- **Planned at**: commit `21884fc`, 2026-08-16

## Why this matters

Height and body targets live in Settings while measurements, trends, BMI, and target lines live in Progress. Progress also permanently shows a large measurement form and empty KPI/history placeholders. Moving the three profile fields into one Progress sheet and the measurement form into another makes Progress dashboard-first and removes duplicate settings routes/forms.

## Current state

- `routes/web.php:47-48` exposes separate Settings body-target and height routes; `:58` exposes a second height route under Progress.
- `SettingsController::edit` loads three `DailyGoal` fields into a `settings` prop. `updateBodyTargets`, `updateHeight`, and private `goal()` at `:76-110` own their writes/default goal creation.
- `ProgressController::index` already passes `preferences` and all three fields in `goals`:

  ```php
  'goals' => $goal ? [
      'height_cm' => /* canonical cm */,
      'target_weight_kg' => /* canonical kg */,
      'target_body_fat_percent' => /* percent */,
  ] : null,
  ```

- `ProgressController::updateHeight` duplicates default nutrition values solely to update height.
- `ProgressController::index:25` displays `DailyGoal::query()->latest('updated_at')->first()`. The table has no singleton constraint and import/sync can leave more than one row, so the write path must use that exact selector rather than unordered `first()`/`firstOrCreate()`.
- `resources/js/Pages/Settings.vue:68-75`, `:160-175`, `:315-324`, and `:499-556` contain two forms, two submissions, an unsaved-unit conversion watcher, and two cards.
- `resources/js/Pages/Progress.vue:53-58` owns the measurement form; `:151-177` always renders three KPI cards, `:206-262` always renders the form, and `:264-286` adds a second empty-history surface.
- `resources/js/bodyUnits.ts` is the existing source for `heightFromCm`, `heightToCm`, `weightFromKg`, and `weightToKg`. Reuse it; do not add conversion functions.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Route audit | `php artisan route:list --except-vendor --path=progress -v` | one body-profile PUT plus metric routes |
| PHP format | `vendor/bin/pint --dirty --format agent routes/web.php app/Http/Controllers/ProgressController.php app/Http/Controllers/SettingsController.php tests/Feature/ProgressTest.php tests/Feature/SettingsTest.php` | exit 0; out-of-scope PHP untouched |
| PHP tests | `php artisan test --compact tests/Feature/ProgressTest.php tests/Feature/SettingsTest.php` | all pass |
| Typecheck | `pnpm run type-check` | exit 0, no errors |
| Build | `pnpm run build` | exit 0 |

## Suggested executor toolkit

- Invoke `inertia-vue-development` for the two `useForm` sheets.
- Invoke `laravel-best-practices` and `pest-testing` for the route/controller consolidation and tests.

## Scope

**In scope**:

- `routes/web.php`
- `app/Http/Controllers/ProgressController.php`
- `app/Http/Controllers/SettingsController.php`
- `resources/js/Pages/Progress.vue`
- `resources/js/Pages/Settings.vue`
- `tests/Feature/ProgressTest.php`
- `tests/Feature/SettingsTest.php`
- `plans/README.md` (status only)

**Out of scope**:

- `DailyGoal`, `BodyMetric`, migrations, sync payloads, import/export fields, and buff-server.
- Moving kg/lb or cm/in preferences out of Settings.
- BMI formulas, chart formulas, target-line semantics, metric deletion, or measurement validation.
- A shared sheet/form abstraction; keep the two small sheet bodies local to Progress.

## Git workflow

- Branch: `codex/003-consolidate-progress-body-profile`
- One logical commit: `Consolidate body tracking in Progress`
- Stage only the explicit in-scope implementation files and `plans/README.md`; never use `git add -A` in this dirty worktree.
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Move behavior coverage to Progress

In `tests/Feature/ProgressTest.php`:

- Replace the `/progress/height` case with `PUT /progress/body-profile` sending all three keys.
- Cover creating a default `DailyGoal` when none exists and updating an existing goal without changing its calorie or macro columns.
- Seed two goals with distinct `updated_at` values and prove only the same latest row returned by `index()` is updated; the older row remains unchanged.
- Cover both explicit null and the browser's three empty-string payload values, asserting all three stored columns become null.
- Use a dataset that omits each required key in turn, asserts that key's `present` validation error, and proves the existing row is unchanged.
- Test exact accepted minima/maxima plus rejected below/above values: height 50/260 cm, target weight 1/1000 kg, body fat 1/80.
- Assert all three removed PUT URIs (`/settings/body-targets`, `/settings/height`, `/progress/height`) return 404 after the route change.
- Keep existing metric create/update/delete, history, delta, preferences, and goal-prop assertions.

In `SettingsTest.php`, remove the two old write cases and change the render assertion so Settings still returns unit/reminder/Health Connect props but no `settings` body-profile prop.

**Verify**: `php artisan test --compact tests/Feature/ProgressTest.php tests/Feature/SettingsTest.php` → new body-profile/removed-route assertions fail against current production behavior; unchanged cases pass.

### Step 2: Collapse three write routes into one Progress endpoint

In `routes/web.php`:

- Delete `PUT /settings/body-targets`.
- Delete `PUT /settings/height`.
- Replace `PUT /progress/height` with `PUT /progress/body-profile` targeting `ProgressController::updateBodyProfile`.

In `ProgressController`, replace `updateHeight` with `updateBodyProfile`. Validate the complete form contract:

```php
'height_cm' => ['present', 'nullable', 'numeric', 'min:50', 'max:260'],
'target_weight_kg' => ['present', 'nullable', 'numeric', 'min:1', 'max:1000'],
'target_body_fat_percent' => ['present', 'nullable', 'numeric', 'min:1', 'max:80'],
```

Select `DailyGoal::query()->latest('updated_at')->first()`, exactly matching `index()`. If it exists, update only the three validated body fields. If none exists, create one row containing the existing 2000 kcal nutrition defaults plus the three validated body fields. Empty form strings are normalized to null by Laravel middleware. Return back with one `Body profile saved.` flash message. Keep inline validation, matching current controller convention; do not introduce a Form Request for one endpoint.

**Verify**: `php artisan test --compact tests/Feature/ProgressTest.php` → all pass; `php artisan route:list --except-vendor --path=progress -v` → exactly GET Progress, POST body metrics, PUT body profile, and DELETE bound body metric, all inside the existing account middleware group.

### Step 3: Remove body-profile ownership from Settings

In `SettingsController`:

- Stop querying `DailyGoal` in `edit()` and remove the `settings` prop.
- Delete `updateBodyTargets`, `updateHeight`, private `goal()`, and the now-unused `DailyGoal` import.
- Preserve units, reminders, Health Connect, account, sync, appearance, and import/export behavior.

In `Settings.vue`:

- Remove the `settings` prop, both body forms, submit functions, two body cards, and their conversion watcher.
- Remove only imports made unused by that deletion. Keep `WeightUnit`/`HeightUnit` types if the preferences prop uses them.
- Keep the existing Units card in Settings; it remains the single owner of display preferences.

**Verify**: `rg -n "settings/body-targets|settings/height|progress/height|updateBodyTargets|updateHeight|bodyTargetForm|heightForm" routes app/Http/Controllers resources/js/Pages` → no matches. Then run `php artisan test --compact tests/Feature/ProgressTest.php tests/Feature/SettingsTest.php` → all pass, including 404 coverage for the removed routes.

### Step 4: Make Progress dashboard-first with two sheets

In `Progress.vue`:

- Rename the generic measurement `form` to `metricForm`.
- Add `bodyProfileForm`, initialized in display units from `props.goals` via `heightFromCm` and `weightFromKg`.
- Add one local state value `openSheet: 'metric' | 'profile' | null`.
- When history exists, add header actions `Log measurement` and `Edit body profile`. Do not also duplicate them in a card.
- Move the existing Log current form unchanged into the metric sheet. Convert display weight to canonical kg in its transform; close only in `onSuccess`, leaving validation errors visible.
- Add the profile sheet with height, target weight, and target body fat. Transform height/weight to canonical cm/kg and PUT all three fields to `/progress/body-profile`; close only in `onSuccess`.
- Give each overlay a labelled `role="dialog"`, `aria-modal="true"`, visible close button, backdrop close, Escape handling, initial focus, focus containment, and focus return to its trigger. Do not add a dependency or share/extract a component.

When `history.length === 0`, render one onboarding card explaining that the first measurement unlocks trends, with the two actions inside that card only. Do not render header actions, the three `--` KPI cards, empty charts, inline form, or a separate `No progress entries yet` card. When history exists, render KPIs, trends, and recent history as today, with the two header actions instead of inline forms.

**Verify**: `pnpm run type-check` → exit 0. Then run `pnpm run build` → exit 0.

### Step 5: Run the automated and mobile interaction gates

**Verify**:

```bash
vendor/bin/pint --dirty --format agent routes/web.php app/Http/Controllers/ProgressController.php app/Http/Controllers/SettingsController.php tests/Feature/ProgressTest.php tests/Feature/SettingsTest.php
php artisan test --compact tests/Feature/ProgressTest.php tests/Feature/SettingsTest.php
pnpm run type-check
pnpm run build
```

Expected: every command exits 0.

Then complete a 390px web pass before marking DONE:

- Empty history shows one card with two non-duplicated actions; populated history shows only the two header actions plus KPIs/trends/history.
- Each sheet opens, takes focus, contains Tab focus, closes via close button/backdrop/Escape, and returns focus to its trigger.
- Validation keeps the relevant sheet open; successful save closes it and refreshes the dashboard.
- Body values display and submit correctly once with kg/cm preferences and once with lb/in preferences.

**Verify**: Record every checklist item as PASS in the implementation handoff. Any failed item is a failed step, not a deferred follow-up.

## Test plan

- Body profile: create defaults, update the displayed latest row when duplicates exist, preserve nutrition fields, clear null/empty strings, accept exact boundaries, reject outside boundaries, and require all three keys.
- Settings: body prop/routes are absent; unit preferences, reminders, Health Connect, import/export, and account settings remain.
- Progress: existing metric create/update/delete and goal/trend props remain.
- Removed routes: all three old PUT URIs return 404.
- Manual 390px: both empty/history states, complete focus/dismissal behavior, validation/success behavior, and both unit modes.

## Done criteria

- [ ] Progress is the only UI and route owner for height/body targets.
- [ ] One `PUT /progress/body-profile` atomically updates all three nullable values.
- [ ] Settings has no body-profile query, prop, form, card, or write route.
- [ ] Measurement and profile forms are hidden behind two clear Progress actions.
- [ ] Empty Progress shows one useful state, not placeholder KPIs plus duplicate empty history.
- [ ] Database/schema/sync/export code and formats are untouched.
- [ ] The mandatory 390px interaction checklist is recorded as PASS.
- [ ] Focused tests, typecheck, and build pass.
- [ ] No new out-of-scope paths appear beyond the initial status baseline; operator files and numbered plans are untouched.
- [ ] `plans/README.md` is updated to DONE.

## STOP conditions

- Product defines Body Profile to include unit preferences; this plan intentionally keeps them in Settings.
- Partial body-profile requests must preserve omitted values; this plan deliberately uses a complete-form `present` contract.
- Clearing a field cannot be represented as explicit null without changing global request middleware.
- The change appears to require a model, migration, remote API, sync, or import/export change.
- Existing code has more callers of any deleted route than the tests/UI identified by `rg`.
- Progress display and body-profile update cannot be made to select the same latest goal row without changing persistence architecture.

## Maintenance notes

- Canonical persistence stays cm/kg; conversions remain UI-only through `bodyUnits.ts`.
- Reviewers should scrutinize nullable clearing, default-goal creation, and open-sheet validation behavior.
- If Settings eventually loses all measurement preferences, reconsider the Units card then; do not move it speculatively now.
