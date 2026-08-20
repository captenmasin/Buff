# 016 — Recipes and copy yesterday

- **Status**: TODO
- **Severity**: HIGH
- **Category**: Logging friction
- **Estimated scope**: new Recipe model/controller, MealController copy + log-recipe endpoints, Add / Today / AppShell, feature tests
- **Depends on**: none

## Problem

Each `MealEntry` is one food. Repeat (`POST /meals/{mealEntry}/repeat`) and previous-meal search cover “I logged this barcode before.” They do not cover:

- Same breakfast as yesterday (four items)
- A homemade meal with several ingredients saved as one thing

`MealController::create` modes are `food | custom | workout | photo`. AppShell add drawer has Search / Scan / Custom / Workout / Photo. Today’s empty day CTA is Add food / Add workout only.

## Target

Two logging shortcuts, shipped together:

1. **Copy yesterday** — duplicate yesterday’s meal entries onto the selected date (meals only, not workouts). Optional copy of a single `meal_type`.
2. **Recipes** — named multi-ingredient template. Logging a recipe creates **one** `MealEntry` (`source_type = recipe`) with scaled macros, not N child rows.

Copy is the smaller, higher-frequency win; implement it first in this plan so Today improves even if recipe UI takes longer.

## Repo conventions to follow

- `MealEntry` sources: add `SOURCE_RECIPE = 'recipe'` next to `SOURCE_CUSTOM` / `SOURCE_BARCODE` in `app/Models/MealEntry.php`.
- New persisted models extend `SyncedModel` (UUID, microsecond timestamps), register in `config/buff.php` `sync_models`.
- JSON on a model is already used (`AppPreference.meal_reminders`). Prefer **one** `recipes` table with an `items` JSON array over a second `recipe_items` table unless you need per-item sync independently — one table is enough.
- Controllers: `php artisan make:model Recipe --no-interaction` then a migration via `php artisan make:migration create_recipes_table --no-interaction`. `php artisan make:controller RecipeController --no-interaction`. Keep store-from-recipe and copy-day on `MealController` to stay next to other meal writes.
- Validate with array rules; `Rule::in(MealEntry::MEAL_TYPES)`.
- Repeat’s `replicate` pattern is the copy implementation. Do not go through the barcode lookup API.
- Vue: `useForm` / `router.post`; empty-day CTA lives in `Today.vue`; add drawer in `AppShell.vue`; Add page modes in `Add.vue` + `MealController::create`.
- Tests: `php artisan make:test --pest RecipeTest --no-interaction` and extend `tests/Feature/MealEntryTest.php` for copy. Follow `it('creates a custom meal...')` style.
- `vendor/bin/pint --dirty --format agent` on PHP.

## Copy yesterday — steps

1. `POST /meals/copy` in `routes/web.php` inside `EnsureBuffAccount`.

   Body: `from_date` (required date), `to_date` (required date, different from from), `meal_type` (nullable, `Rule::in(MEAL_TYPES)`).

2. `MealController::copy`:

   - Load entries on `from_date`, optionally `where meal_type`.
   - If none: `ValidationException` on `from_date`: `No meals to copy.`
   - For each, `replicate` excluding `id`, `date`, timestamps (same as `repeat()`). Set `date` to `to_date`. Keep `meal_type`, source, product, portions, macros, `recipe_id` if present.
   - Do **not** copy `WorkoutEntry`.
   - Redirect `/?date={to_date}` with message `Meals copied.` / `Breakfast copied.` etc.

3. Today UI:

   - Empty-day card (`isEmptyDay`): add a full-width **Copy yesterday** button. `from_date` = selected date minus 1 day, `to_date` = `summary.date`. Use `router.post('/meals/copy', { from_date, to_date })`.
   - When the day already has meals: a quieter control (text `Button variant="ghost"` near the meal list header, or a ConfirmSheet if `to_date` already has entries — confirm “Add yesterday’s meals to this day?”). Always **append**; never delete existing items.
   - Per meal section that is empty: optional `Copy yesterday’s {breakfast}` posting `meal_type`. Skip if this bloats Today too much; empty-day + one whole-day action is the minimum. Prefer also per-slot copy on empty sections — it is the “same breakfast” case.

4. Tests in `MealEntryTest` (or a focused test file):

   - Two meals on 18 May, copy to 19 May → two new rows, same names/macros, new ids, redirect `/?date=2026-05-19`.
   - Copy `meal_type=breakfast` copies only breakfast.
   - Copying a day with no meals → 422/session errors on `from_date`.
   - `from_date === to_date` → error.
   - Workouts on from_date are not duplicated.

## Recipes — steps

1. Migration `recipes`:

```php
$table->uuid('id')->primary();
$table->string('name');
$table->decimal('servings', 8, 2)->default(1);
$table->json('items');
$table->timestamps(6);
```

`items` is a JSON array of:

```php
[
  'name' => string,
  'food_product_id' => ?uuid,
  'portion_quantity' => float,
  'portion_unit' => 'g'|'ml',
  'calories' => int,
  'protein_g' => float,
  'carbs_g' => float,
  'fat_g' => float,
]
```

2. `Recipe extends SyncedModel`, `$fillable` those columns, `items` + `servings` casts (`array`, `decimal:2`). Totals are derived when logging, not stored on the recipe row (or store cached totals if you want list UI — derive in an accessor to avoid drift).

3. `config/buff.php` `sync_models`:

```php
Recipe::class => [
    'type' => 'recipes',
    'fields' => ['name', 'servings', 'items'],
],
```

Add `recipe_id` nullable uuid on `meal_entries` (migration) and append `'recipe_id'` to `MealEntry` sync fields. `MealEntry` `belongsTo` Recipe, nullable.

4. `RecipeController`:

   - `index` not required as a page if Add lists them.
   - `store` / `update` / `destroy` — validate name, servings `min:0.1`, items `array|min:1`, each item the shape above (reuse `NutritionCalculator` if item is a product portion; custom items send macros like custom meals).
   - Keep UI on `/add?mode=recipe` rather than a new nav tab.

5. `MealController::create`: add `'recipe'` to `$availableModes`. Pass `recipes` => `Recipe::query()->latest()->get()` mapped to `{ id, name, servings, calories, protein_g, carbs_g, fat_g, items }` with totals summed from items.

6. `POST /meals/recipe`:

```
date, meal_type, recipe_id, servings (logged servings, default recipe.servings)
```

Scale factor = `logged_servings / recipe.servings`. Create one `MealEntry`: `source_type=recipe`, `name=recipe.name`, macros rounded like other stores, `portion_quantity=logged_servings`, `portion_unit` can be stored as `g` only if you must — **prefer** nullable portion on recipe logs, or use servings in `portion_quantity` with a comment in code. Cleanest: `portion_quantity` = logged servings, `portion_unit` = `'g'` is a lie. Add no new unit. Store servings in `portion_quantity` and `portion_unit` null (column already nullable). Repeat/copy must still work (replicate).

Calories: `(int) round(sum(item.calories) * factor)` etc.

7. Add UI (`Add.vue` mode `recipe`):

   - List saved recipes; tap → meal type + servings stepper → save logs via `/meals/recipe`.
   - **New recipe**: name, servings (default 1), add items using the existing food search endpoint `GET /food-products/search` and/or custom macro fields (same controls as custom meal). Save via `POST /recipes`.
   - AppShell drawer: a **Recipe** row under Photo (Utensils / Book icon from `@lucide/vue` — `CookingPot` or `UtensilsCrossed`; do not add a new icon pack).
   - Choose-mode grid on `/add` gets the same Recipe button.

8. Today meal detail: recipe entries look like other meals (name, macros). No ingredient expander required in this plan. Edit existing meal sheet can keep editing the rolled-up macros (current edit form).

9. Tests (`tests/Feature/RecipeTest.php`):

   - Create recipe with two items, log 1 serving → one meal_entry, summed macros, `source_type=recipe`.
   - Log 2 servings of a 1-serving recipe → macros doubled.
   - Delete recipe does not delete past meal entries (`nullOnDelete` or null `recipe_id`). Use `nullOnDelete()` on the FK.
   - Add mode `recipe` Inertia prop contains the recipe.

## Boundaries

- Do NOT explode a recipe into multiple Today rows.
- Do NOT copy workouts.
- Do NOT build a recipe importer from MyFitnessPal.
- Do NOT add favorites as a separate model; recipes + previous meals are enough.
- Do NOT add a fifth bottom-nav tab.
- Do NOT change photo analysis.
- If the remote Buff API does not yet accept `recipes` / `recipe_id`, still register client sync like other models (local outbox). Do not build a parallel unsynced store.

## Verification

- **Mechanical**: `vendor/bin/pint --dirty --format agent`; `php artisan test --compact tests/Feature/MealEntryTest.php tests/Feature/RecipeTest.php tests/Feature/BuffSyncTest.php` (sync config still loads). `pnpm run type-check` if you touch TS props.
- **Feel check**:
  - Empty today, Copy yesterday after logging four items on yesterday: today matches, yesterday unchanged.
  - Copy again: items duplicate (append). Confirm sheet if you added one.
  - Save “Overnight oats” with oats + milk + yogurt; log it at dinner 1 serving; one row; macros match the sum.
  - Log 0.5 servings; macros half.
- **Done when**: copy-yesterday works for full day and (if implemented) meal slot; recipes save and log as one entry; tests pass.
