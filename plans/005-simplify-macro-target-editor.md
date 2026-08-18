# Plan 005: Replace macro wheels with presets and a balanced custom split

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving on. If a STOP condition occurs, stop and report; do not improvise. When done, update this plan's row in `plans/README.md` unless a reviewer owns the index.
>
> **Drift check (run first)**: Run both `git diff --stat 21884fc -- resources/js/Pages/Goals.vue resources/js/goalMacros.ts tests/goalMacros.test.ts package.json tests/Feature/GoalTest.php tests/Unit/NutritionCalculatorTest.php` and `git status --short --untracked-files=all -- resources/js/Pages/Goals.vue resources/js/goalMacros.ts tests/goalMacros.test.ts package.json tests/Feature/GoalTest.php tests/Unit/NutritionCalculatorTest.php`.
> If an in-scope file changed, compare the excerpts below with live code. Any behavioral mismatch is a STOP condition.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: none
- **Category**: direction
- **Planned at**: commit `21884fc`, 2026-08-16

## Why this matters

Goals uses three independent scroll wheels, so users can create totals other than 100% and must resolve a separate mismatch state before saving. Three numeric ratio presets plus two native custom controls make valid allocation the default. Numeric labels avoid dietary claims, but the exact ratios are still a product/nutrition decision and must be explicitly approved before implementation.

## Current state

- `resources/js/Pages/Goals.vue:13-21` declares macro metadata, 21 percentage options, wheel height, and element refs.
- `Goals.vue:34-41` tracks three independent percentages and computes both total and calorie match.
- `Goals.vue:43-121` clamps, derives, scrolls, and synchronizes wheel state; mount code at `:131-137` mutates form grams and scroll positions.
- `Goals.vue:181-208` renders three wheel columns; `:210-225` renders total/mismatch; `:231` blocks Save unless client math matches. Wheel-only CSS is at `:239-247`.
- `GoalController::update:38-55` validates gram bounds and rejects any payload whose rounded 4/4/9 macro calories do not equal the calorie goal.
- `app/Services/NutritionCalculator.php:10-17` is authoritative:

  ```php
  round(($protein * 4) + ($carbs * 4) + ($fat * 9)) === round($calories)
  ```

- The repo has Node 22.22.2, ESM, TypeScript 6, and no frontend test dependency. Node's built-in test runner can test a pure `.ts` helper without adding a package.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Runtime gate | `node --version` | v22.18.0 or newer |
| Frontend logic | `pnpm run test:frontend` | all tests pass |
| PHP format | `vendor/bin/pint --dirty --format agent tests/Feature/GoalTest.php tests/Unit/NutritionCalculatorTest.php` | exit 0; out-of-scope PHP untouched |
| PHP tests | `php artisan test --compact tests/Feature/GoalTest.php tests/Unit/NutritionCalculatorTest.php` | all pass |
| Typecheck | `pnpm run type-check` | exit 0, no errors |
| Build | `pnpm run build` | exit 0 |

## Suggested executor toolkit

- Invoke `inertia-vue-development` for `useForm`, reactive preset state, and accessible controls.
- Invoke `pest-testing` for PHP boundary/regression coverage.

## Scope

**In scope**:

- `resources/js/Pages/Goals.vue`
- `resources/js/goalMacros.ts` (create)
- `tests/goalMacros.test.ts` (create; do not add a new test-suite directory)
- `package.json`
- `tests/Feature/GoalTest.php`
- `tests/Unit/NutritionCalculatorTest.php`
- `plans/README.md` (status only)

**Out of scope**:

- `GoalController`, `NutritionCalculator`, `DailyGoal`, migrations, and server validation semantics.
- Applying presets to Onboarding; avoid coupling this plan to Plan 004.
- Dietary names or claims such as weight loss, performance, low carb, or healthy.
- A UI/control dependency. Use native range inputs and existing Button/Input/Card components.

## Git workflow

- Branch: `codex/005-simplify-macro-target-editor`
- One logical commit: `Simplify macro target selection`
- Stage only the explicit in-scope implementation files and `plans/README.md`; never use `git add -A` in this dirty worktree.
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Add one pure, dependency-free macro math module and check

Before editing, obtain explicit operator approval for these exact Protein / Carbs / Fat presets: `30 / 40 / 30`, `40 / 30 / 30`, and `30 / 30 / 40`. If approval is absent or different ratios are requested, STOP and report; do not treat numeric labels as nutrition approval.

Then run `node --version`. Stop if the executor or CI baseline is older than 22.18; do not add a transpiler/test framework to compensate.

Create `resources/js/goalMacros.ts` with only:

- `MacroSplit` (`protein`, `carbs`, `fat`) and `MacroGrams` types.
- Three frozen, numerically labelled presets in **Protein / Carbs / Fat** order, after the approval gate above:
  - `30 / 40 / 30`
  - `40 / 30 / 30`
  - `30 / 30 / 40`
- A small normalization function that snaps Protein and Carbs to 5% steps, constrains them to 0–100, caps Carbs to the percentage remaining after Protein, and derives fat as `100 - protein - carbs`.
- A split-from-grams initializer that returns that nearest valid 5% Custom split without changing the supplied grams.
- `gramsForSplit(calories, split)`: round protein and carbs grams to two decimals, then calculate fat from the remaining calories and round to two decimals. Let fat absorb decimal remainder so rounded 4/4/9 calories match the integer target.
- `macroCalories(grams)`, mirroring PHP `Math.round((p * 4) + (c * 4) + (f * 9))`.
- `splitWithinGramBounds(calories, split)`, which returns false when any generated macro is outside the server's 0–1000g bounds.
- `hasValidFivePercentSplit(calories)`, a bounded 5%-step search used only to distinguish “adjust this split” from “this calorie target has no representable split.”

Do not add a class, composable, config file, or generalized nutrition library.

Create `tests/goalMacros.test.ts` using `node:test` and `node:assert/strict`. Cover all three presets, zero/boundary splits, invalid custom input normalization, a normal 2000 kcal target, and an odd 1999 kcal target. For every valid generated case, assert two-decimal-or-fewer grams and `macroCalories(grams) === Math.round(calories)`.

Also cover the server's 1000g-per-macro ceiling: 100% protein is valid at 4000 kcal and invalid at 4001; the 30/40/30 and 40/30/30 presets are valid at 10000 and invalid at 10001; the 30/30/40 preset is valid at 13333 and invalid at 13334; a valid 5% split exists at 16000 while none exists at 20000. These are UI guards only; do not change controller bounds.

Add exactly one package script:

```json
"test:frontend": "node --test tests/goalMacros.test.ts"
```

**Verify**: `pnpm run test:frontend` → all tests pass without installing anything.

### Step 2: Replace independent wheel state with preset/custom state

In `Goals.vue`:

- Import the presets/math helper.
- Keep the existing Inertia form payload keys: `calories`, `protein_g`, `carbs_g`, `fat_g`.
- Generate each preset's grams for the current calories and select it only when all three generated values equal the stored values to two-decimal storage precision. Near matches are Custom; never mark an exact preset as pressed while the form contains different grams.
- Initialize custom percentage controls from the existing grams but do **not** call a synchronization function on mount. Merely opening Goals must leave the current form grams unchanged.
- `applySplit` is the one write path from a selected split to the three form gram fields.
- Selecting a preset applies it immediately. Changing calories reapplies the currently displayed split.
- Custom exposes Protein and Carbs; Fat is always derived. Use each native range input's dynamic `max` (`100 - other value`) and `step=5`, so invalid totals are not representable in normal interaction.

Delete `percentageOptions`, scroller element refs, `nextTick`, mount scrolling, scroll handlers, three-way total state, and wheel CSS.

**Verify**: `rg -n "percentageOptions|macroScrollerElements|scrollToPercent|handleScrollerScroll|macro-wheel|nextTick|onMounted" resources/js/Pages/Goals.vue` → no matches. Then run `pnpm run test:frontend` → all pass. Then run `pnpm run type-check` → exit 0.

### Step 3: Render presets first and Custom second

Replace the wheel portion of the Macros card with:

- A small `Protein / Carbs / Fat` legend so numeric ratios are unambiguous.
- Three preset Buttons and one Custom Button, each with `type="button"` and `aria-pressed`.
- Disable a preset when `splitWithinGramBounds` is false for the current calorie target; keep its ratio visible.
- When Custom is active, two labelled native range inputs with their current percentages visible; show Fat as read-only derived output.
- A compact Protein/Carbs/Fat summary showing percentage and generated grams.
- A quiet `100% allocated` summary instead of a success/destructive mismatch badge.
- Existing server field errors, especially `form.errors.calories`, remain visible.
- If the selected split exceeds a 1000g macro bound, show `Adjust calories or the macro split; each macro must be 1000g or less.` and disable Save. If `hasValidFivePercentSplit` is false, show `This calorie target cannot be represented with 5% macro steps.` instead.
- Save is disabled while `form.processing` or the client bound guard fails; backend equality and gram validation remain the final trust boundary.

Remove the unused Badge import. Keep the calories input and redirect behavior unchanged.

**Verify**: `pnpm run type-check` → exit 0. Then run `pnpm run build` → exit 0.

### Step 4: Prove client-generated decimals satisfy the server

Extend `GoalTest.php` with one representative preset payload and one odd-calorie/two-decimal custom payload generated by the helper's documented formula. Use these exact custom values for a 1999 kcal, 35 / 45 / 20 split: `protein_g=174.91`, `carbs_g=224.89`, `fat_g=44.42`; their 4/4/9 total is 1998.98 and rounds to 1999. Both cases must save and persist one goal. Retain the forged mismatch rejection test.

Extend `NutritionCalculatorTest.php` with the same odd-calorie values and assert `goalMatchesCalories` is true. Do not change PHP production math to make a failing frontend result pass; fix `goalMacros.ts` instead.

**Verify**: `php artisan test --compact tests/Feature/GoalTest.php tests/Unit/NutritionCalculatorTest.php` → all pass.

### Step 5: Format and run the complete gate

**Verify**:

```bash
vendor/bin/pint --dirty --format agent tests/Feature/GoalTest.php tests/Unit/NutritionCalculatorTest.php
pnpm run test:frontend
pnpm run type-check
pnpm run build
php artisan test --compact tests/Feature/GoalTest.php tests/Unit/NutritionCalculatorTest.php
```

Expected: every command exits 0.

## Test plan

- Pure frontend math: every approved preset totals 100, custom normalization cannot go negative/over 100, normal and odd calorie goals match rounded PHP semantics, grams have at most two decimals, and 1000g/no-representable-split boundaries are correct.
- Server: preset/custom payloads save; forged mismatch and existing bounds still reject.
- Manual 390px: each approved preset, Custom activation, keyboard/range adjustment, dynamic maxima, calorie change, disabled over-bound presets, selected-split bound error, no-representable-split error, processing state, and saved values after reload.
- Existing non-preset goal: opens as Custom and no form grams change until the user selects/edits a split or changes calories.

## Done criteria

- [ ] Three explicitly approved numeric presets and Custom replace all wheel UI/state/CSS.
- [ ] Protein + Carbs + derived Fat always equals 100 in normal UI interaction.
- [ ] Generated 4/4/9 calories round exactly to the calorie target, including odd targets.
- [ ] Existing custom grams are not mutated merely by mounting the page.
- [ ] A preset is selected on mount only for an exact generated-gram match; near matches remain Custom.
- [ ] Presets/custom saves cannot submit a macro above 1000g, and impossible 5%-step calorie targets explain why Save is disabled.
- [ ] Server validation remains unchanged and rejects forged mismatch payloads.
- [ ] No runtime/test dependency was added; Node built-in tests pass.
- [ ] Focused PHP tests, typecheck, and build pass.
- [ ] No new out-of-scope paths appear beyond the initial status baseline; operator files and numbered plans are untouched.
- [ ] `plans/README.md` is updated to DONE.

## STOP conditions

- The operator has not explicitly approved the three exact preset ratios, or requests named diet/health presets; obtain product/nutrition signoff rather than inventing claims.
- Custom must expose all three independently editable percentages; redistribution priority must be specified first.
- Existing goals should automatically snap/persist to a preset on page load.
- Node or CI is older than 22.18; adding a test toolchain/dependency is outside this plan.
- Matching the backend appears to require relaxing gram bounds or calorie equality.

## Maintenance notes

- Keep preset definitions client-only until another screen genuinely needs the same UX.
- Reviewers should scrutinize odd-calorie rounding and the no-mutation-on-mount invariant.
- If presets are later added to onboarding, reuse the tiny math module, not the entire Goals page UI.
