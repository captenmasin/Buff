# Plan 002: Collapse Today meals into one list and one management sheet

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving on. If a STOP condition occurs, stop and report; do not improvise. When done, update this plan's row in `plans/README.md` unless a reviewer owns the index.
>
> **Drift check (run first)**: Run both `git diff --stat 21884fc -- resources/js/Pages/Today.vue resources/js/Components/ui/dropdown-menu/DropdownMenu.vue resources/js/Components/ui/dropdown-menu/DropdownMenuItem.vue tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php` and `git status --short --untracked-files=all -- resources/js/Pages/Today.vue resources/js/Components/ui/dropdown-menu/DropdownMenu.vue resources/js/Components/ui/dropdown-menu/DropdownMenuItem.vue tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php`.
> If an in-scope file changed, compare the excerpts below with live code. Any behavioral mismatch is a STOP condition.

## Status

- **Priority**: P1
- **Effort**: L
- **Risk**: MED
- **Depends on**: none
- **Category**: direction
- **Planned at**: commit `21884fc`, 2026-08-16

## Why this matters

Today renders four meal cards even when most are empty, then uses a row, kebab menu, information modal, and separate edit modal to manage one meal. That repetition makes the primary daily log longer and creates parallel modal state. One populated-only Meals card and one details/edit sheet retain all actions with less scanning and less state.

## Current state

- `resources/js/Pages/Today.vue:144-147` derives `hasMeals`, `hasWorkouts`, and `isEmptyDay`, but not the populated meal types.
- `Today.vue:157-162` has three overlapping management states:

  ```ts
  const selectedMeal = ref<SelectedMeal | null>(null);
  const editingMeal = ref<SelectedMeal | null>(null);
  const openMealActions = ref<string | null>(null);
  ```

- `Today.vue:401-466` separately loads details/photos and initializes/saves edit state; `:486-489` toggles each kebab.
- `Today.vue:556-570` already has the correct full-empty-day `Start today` card.
- `Today.vue:608-668` loops all four `mealTypes`, renders per-group Add buttons, and prints `No entries yet` for empty groups.
- `Today.vue:737-823` renders separate details and edit overlays.
- `app/Services/DailySummaryService.php:51-67` already groups `summary.entries` only under populated meal keys. No response-shape change is needed.
- `DropdownMenu.vue` and `DropdownMenuItem.vue` are currently consumed only by Today, subject to a live `rg` check before deletion.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| PHP format | `vendor/bin/pint --dirty --format agent tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php` | exit 0; out-of-scope PHP untouched |
| PHP tests | `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/MealPhotoIntegrationTest.php tests/Unit/HealthConnectTodayRefreshTest.php` | all pass |
| Typecheck | `pnpm run type-check` | exit 0, no errors |
| Build | `pnpm run build` | exit 0 |

## Suggested executor toolkit

- Invoke `inertia-vue-development` for the Vue/Inertia form and navigation behavior.
- Invoke `pest-testing` when changing feature/unit tests.

## Scope

**In scope**:

- `resources/js/Pages/Today.vue`
- `resources/js/Components/ui/dropdown-menu/DropdownMenu.vue` (delete only if unused)
- `resources/js/Components/ui/dropdown-menu/DropdownMenuItem.vue` (delete only if unused)
- `tests/Feature/DashboardTest.php`
- `tests/Feature/MealEntryTest.php`
- `plans/README.md` (status only)

**Out of scope**:

- Daily summary/controller response shapes, database models, routes, and meal/photo endpoints.
- Macro cards, week strip, date selection, workout deletion, and Health Connect behavior.
- Add's pre-log product/previous-meal sheets; those choose portions before logging and are a different job.
- A new modal/dropdown dependency or a reusable mega-component.
- `tests/Feature/MealPhotoIntegrationTest.php` and `tests/Unit/HealthConnectTodayRefreshTest.php`; run them as read-only regression gates.

## Git workflow

- Branch: `codex/002-simplify-today-meals`
- One logical commit: `Simplify Today meal management`
- Stage only the explicit in-scope implementation files and `plans/README.md`; never use `git add -A` in this dirty worktree.
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Preserve server behavior with focused coverage

- In `DashboardTest.php`, retain the populated summary case and add a goal-backed empty date that asserts `summary.entries` and `summary.workouts` are empty. Add a mixed-meal case asserting the response groups only the created meal types.
- In `MealEntryTest.php`, retain update coverage and add missing delete coverage: delete a bound meal ID, assert redirect, and assert the row is gone.
- Do not write brittle assertions against Vue source text or rendered CSS classes. There is no Vue DOM runner installed; server tests protect the contracts while typecheck/build protect the rewrite.

**Verify**: `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php` → all pass before the Vue rewrite.

### Step 2: Render one populated-only Meals card

In `Today.vue`, add:

```ts
const populatedMealTypes = computed(() => props.mealTypes.filter(
    (mealType) => Boolean(props.summary.entries[mealType]?.length),
));
```

Replace the four outer meal cards with:

- One Meals section shown only when `hasMeals`.
- One header containing a single `Add food` Link to `/add?mode=food&date=${summary.date}`.
- One Card containing each item in `populatedMealTypes`; keep the meal-type icon/heading and current entry order. Each row must retain its current name, calories, and portion quantity/unit scan-level text.
- No empty group rows, no `No entries yet`, and no per-group Add button.

Keep `Start today` as the only CTA for a **goal-backed** empty day (`hasGoal && isEmptyDay`). Preserve the existing no-goal warning branch. A workout-only day intentionally has no duplicate on-page Meals card; global Add remains available. Keep Workouts and Android Health Connect controls rendered exactly as they are.

**Verify**: `pnpm run type-check` → exit 0; `rg -n "No entries yet" resources/js/Pages/Today.vue` → no matches.

### Step 3: Replace kebab/details/edit state with one sheet state machine

Keep `selectedMeal` and photo state. Replace `editingMeal` and `openMealActions` with one explicit mode:

```ts
const mealSheetMode = ref<'details' | 'edit' | null>(null);
```

- Row activation sets `selectedMeal`, sets mode to `details`, resets photo state, and starts the existing photo request guarded by `mealPhotoRequest`.
- A single close function increments `mealPhotoRequest` to invalidate stale responses, clears selected photos/loading, calls both `editMealForm.reset()` and `editMealForm.clearErrors()`, clears `selectedMeal`, and sets the mode to null. Do not describe this token as Axios cancellation and do not invent photo-error state.
- `startEditingMeal` fills the existing edit form from `selectedMeal` and switches the same sheet to `edit`.
- `saveMealEdit` uses `selectedMeal.id`, stays open on validation error, and closes on success.
- Delete remains guarded by the current native `window.confirm`. Cancel keeps the sheet open; confirm sends the same DELETE and closes on success.
- Closing a dirty edit discards it, matching the current modal behavior. Do not add a second confirmation flow.
- The edit form remains name + meal type + protein/carbs/fat. Calories stay server-derived from macros through the existing PUT endpoint; do not add an editable calorie field.

Delete `toggleMealActions`, the row kebab, and dropdown imports. Make each meal row a clear button/interactive target with an accessible name; do not nest buttons.

**Verify**: `rg -n "editingMeal|openMealActions|toggleMealActions|DropdownMenu" resources/js/Pages/Today.vue` → no matches; `pnpm run type-check` → exit 0.

### Step 4: Render one accessible details/edit overlay

Merge the existing two overlays into one bottom-anchored mobile sheet:

- One backdrop and one sheet container shown when `mealSheetMode !== null && selectedMeal`.
- Give the container `role="dialog"`, `aria-modal="true"`, and `aria-labelledby` pointing to its heading.
- Keep a visible close button and backdrop close. Add local key handling for Escape and Tab focus containment; focus the first sheet control on open and return focus to the activating meal row on close. Do not add a focus-management dependency.
- Details mode retains meal type, name, brand, product image, calories, portion quantity/unit, macro cards, photo loading, and photos-present branches. No-photo success remains silent as it is today; do not add copy or state. Its footer contains Edit and Delete.
- Edit mode replaces the content inside the same container with name, meal-type picker, protein/carbs/fat inputs, validation messages, Cancel, and Save.
- Do not duplicate form state or photo-fetch logic inside the template.

After the rewrite, run `rg -n "DropdownMenu" resources/js`. If no consumers remain, delete the two dropdown-menu files. If another consumer exists, keep them without modification.

**Verify**: `pnpm run type-check` → exit 0. Then run `pnpm run build` → exit 0.

### Step 5: Run the regression gate

Format changed PHP tests and run the complete focused set.

**Verify**:

```bash
vendor/bin/pint --dirty --format agent tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php
php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/MealPhotoIntegrationTest.php tests/Unit/HealthConnectTodayRefreshTest.php
pnpm run type-check
pnpm run build
```

Expected: every command exits 0.

## Test plan

- Server: empty summary, only populated meal groups, meal update, meal delete, photo proxy, and Health Connect refresh contract.
- Manual at 390px: empty day; workout-only day; one meal group; several meal groups; historical date Add link.
- Manual sheet states: details while photos load, photos present, no photos, edit validation failure, edit success, delete cancel, delete confirm, backdrop/close dismissal.
- Manual keyboard check: every row/action is focusable once, the dialog has a label, Tab stays inside it, Escape closes it, focus returns to the activating row, and there are no nested interactive controls.

## Done criteria

- [ ] Goal-backed empty days show one Start Today surface, not four empty meal cards; the no-goal warning is unchanged.
- [ ] Populated days show one Meals card containing only populated groups and one Add food action.
- [ ] A meal row opens one sheet that transitions between details and edit.
- [ ] The sheet contains focus, supports Escape, and restores focus on close without a new dependency.
- [ ] Kebab state and separate details/edit overlays are gone.
- [ ] Stale photo-response invalidation, delete confirmation, date, workouts, and Health Connect are preserved.
- [ ] Unused dropdown components are deleted only after a zero-consumer `rg` result.
- [ ] Focused tests, typecheck, and build pass.
- [ ] No new out-of-scope paths appear beyond the initial status baseline; operator files and numbered plans are untouched.
- [ ] `plans/README.md` is updated to DONE.

## STOP conditions

- `DailySummaryService` no longer guarantees entries grouped by meal type.
- Another page consumes the dropdown components and the planned deletion would break it.
- Product requires empty meal slots or a meal-type-specific one-tap Add action to remain visible.
- The rewrite appears to require endpoint or response-shape changes.
- Automated DOM coverage is mandatory; adding Pest Browser or Vue Test Utils requires separate dependency approval.

## Maintenance notes

- Keep pre-log portion selection in Add and post-log management in Today even if their sheets look similar.
- Reviewers should focus on stale-response invalidation, historical-date links, and interactive semantics.
- If dirty-edit confirmation is requested later, add it as a deliberate cross-modal policy rather than a one-off branch here.
