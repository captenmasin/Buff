<?php

namespace App\Http\Controllers;

use App\Models\FoodProduct;
use App\Models\MealEntry;
use App\Models\PendingMealAnalysisConfirmation;
use App\Models\Recipe;
use App\Services\AnalyticsEventService;
use App\Services\BuffSyncService;
use App\Services\MealSuggestions;
use App\Services\NutritionCalculator;
use App\Services\OpenFoodFactsService;
use App\Services\PortionParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MealController extends Controller
{
    public function __construct(private readonly AnalyticsEventService $analytics) {}

    public function create(Request $request, MealSuggestions $suggestions): Response
    {
        $validated = $request->validate(['date' => ['nullable', 'date']]);
        $mode = $request->string('mode')->toString();
        $mode = match ($mode) {
            'barcode', 'search' => 'food',
            default => $mode,
        };

        $meal = $request->string('meal')->toString();
        $meal = in_array($meal, MealEntry::MEAL_TYPES, true) ? $meal : null;
        $availableModes = ['food', 'custom', 'workout', 'photo', 'recipe', 'label'];
        $date = isset($validated['date']) ? Carbon::parse($validated['date']) : today();
        $entryId = $request->string('entry')->toString();
        $entry = $mode === 'food' && Str::isUuid($entryId) ? $this->previousFoodEntryQuery()->find($entryId) : null;
        $recipeId = $request->string('recipe')->toString();

        return Inertia::render('Add', [
            'date' => $date->toDateString(),
            'mealTypes' => MealEntry::MEAL_TYPES,
            'meal' => $meal,
            'mode' => in_array($mode, $availableModes, true) ? $mode : 'choose',
            'autoScan' => $request->boolean('scan'),
            'selectedPreviousEntry' => $entry !== null ? $this->previousFoodEntryPayload($entry) : null,
            'selectedRecipeId' => $mode === 'recipe' && Str::isUuid($recipeId) ? $recipeId : null,
            'reminderSuggestion' => fn (): ?array => $mode === 'food' && $entry === null && $meal !== null && $request->boolean('reminder')
                && ! MealEntry::query()->whereDate('date', $date->toDateString())->where('meal_type', $meal)->exists()
                    ? ($suggestions->forMissingMeals($date, [$meal])[$meal] ?? null)
                    : null,
            'previousFoodEntries' => $this->previousFoodEntries(),
            'previousCustomMeals' => $this->previousCustomMeals(),
            'recipes' => Recipe::query()
                ->latest()
                ->get()
                ->map(fn (Recipe $recipe): array => $recipe->toPayload())
                ->all(),
        ]);
    }

    public function lookupBarcode(Request $request, OpenFoodFactsService $openFoodFacts, PortionParser $portionParser): JsonResponse
    {
        if (is_string($request->input('barcode'))) {
            $request->merge(['barcode' => preg_replace('/\s+/', '', $request->input('barcode'))]);
        }

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'regex:/\A\d{4,32}\z/'],
        ], ['barcode.regex' => 'Enter the digits printed below the barcode.']);

        $product = $openFoodFacts->lookup($validated['barcode']);

        return response()->json([
            'product' => $this->productPayload($product),
            'portion_options' => $portionParser->optionsForProduct($product),
        ]);
    }

    public function searchFoodProducts(Request $request, OpenFoodFactsService $openFoodFacts): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'string', 'max:35', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['products' => []]);
        }

        $localProducts = FoodProduct::query()
            ->whereIn('id', MealEntry::query()->select('food_product_id'))
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('brand', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->all();

        $locale = $validated['locale'] ?? $request->getPreferredLanguage() ?? 'en_GB';
        $remoteProducts = $openFoodFacts->search($query, 20, str_replace('-', '_', $locale));

        $products = collect([...$localProducts, ...$remoteProducts])
            ->unique('id')
            ->map(fn (FoodProduct $product): array => $this->productPayload($product))
            ->take(20);

        $previousMeals = $this->previousMealsForSearch($query);

        $results = $previousMeals
            ->concat($products)
            ->take(20)
            ->values()
            ->all();

        return response()->json(['products' => $results]);
    }

    public function storeCustom(Request $request, NutritionCalculator $calculator, BuffSyncService $sync): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(MealEntry::MEAL_TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'portion_quantity' => ['required', 'numeric', 'min:0.1', 'max:10000'],
            'portion_unit' => ['required', Rule::in(['g', 'ml', 'serving'])],
            'calories' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'carbs_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'analysis_id' => ['nullable', 'uuid'],
        ]);

        $analysisId = $validated['analysis_id'] ?? null;
        unset($validated['analysis_id']);

        $meal = MealEntry::query()->create([
            ...$validated,
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'portion_unit' => $validated['portion_unit'] === 'serving' ? null : $validated['portion_unit'],
            'calories' => $validated['calories'] ?? $calculator->macroCalories($validated['protein_g'], $validated['carbs_g'], $validated['fat_g']),
        ]);

        if (is_string($analysisId)) {
            PendingMealAnalysisConfirmation::query()->updateOrCreate(
                ['analysis_id' => $analysisId],
                ['meal_record_id' => $meal->id, 'last_error' => null],
            );

            defer(fn () => $sync->sync(), 'buff-sync');
        }

        $this->analytics->record($analysisId ? 'meal_added_photo' : 'meal_added_custom');

        return $this->loggedMeal(
            $meal,
            $analysisId ? 'Meal added. Its photos will attach after sync.' : 'Custom food added.',
        );
    }

    public function storeBarcode(Request $request, NutritionCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(MealEntry::MEAL_TYPES)],
            'food_product_id' => ['required', 'uuid', 'exists:food_products,id'],
            'portion_quantity' => ['required', 'numeric', 'min:0.1', 'max:10000'],
            'portion_unit' => ['required', Rule::in(['g', 'ml'])],
            'add_method' => ['sometimes', 'required', Rule::in(['barcode', 'search'])],
        ]);

        $product = FoodProduct::query()->findOrFail($validated['food_product_id']);

        if ($validated['portion_unit'] !== $product->nutrition_unit) {
            throw ValidationException::withMessages([
                'portion_unit' => "Use {$product->nutrition_unit} for this product because its nutrition data is per 100{$product->nutrition_unit}.",
            ]);
        }

        $meal = MealEntry::query()->create([
            'date' => $validated['date'],
            'meal_type' => $validated['meal_type'],
            'source_type' => MealEntry::SOURCE_BARCODE,
            'food_product_id' => $product->id,
            'name' => $product->name,
            'portion_quantity' => $validated['portion_quantity'],
            'portion_unit' => $validated['portion_unit'],
            ...$calculator->macrosForPortion($product, $validated['portion_quantity']),
        ]);

        $this->analytics->record(($validated['add_method'] ?? 'barcode') === 'search' ? 'meal_added_search' : 'meal_added_barcode');

        return $this->loggedMeal($meal, 'Meal added.');
    }

    public function storeFromRecipe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(MealEntry::MEAL_TYPES)],
            'recipe_id' => ['required', 'uuid', 'exists:recipes,id'],
            'servings' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'quick_log' => ['sometimes', 'boolean'],
        ]);

        $recipe = Recipe::query()->findOrFail($validated['recipe_id']);
        $loggedServings = (float) $validated['servings'];
        $factor = $loggedServings / max((float) $recipe->servings, 0.1);
        $totals = $recipe->totals();

        $meal = MealEntry::query()->create([
            'date' => $validated['date'],
            'meal_type' => $validated['meal_type'],
            'source_type' => MealEntry::SOURCE_RECIPE,
            'recipe_id' => $recipe->id,
            'name' => $recipe->name,
            'portion_quantity' => $loggedServings,
            'portion_unit' => null,
            'calories' => (int) round($totals['calories'] * $factor),
            'protein_g' => round($totals['protein_g'] * $factor, 2),
            'carbs_g' => round($totals['carbs_g'] * $factor, 2),
            'fat_g' => round($totals['fat_g'] * $factor, 2),
        ]);

        $this->analytics->record('meal_added_recipe');

        return $this->loggedMeal($meal, 'Recipe logged.', $request->boolean('quick_log'));
    }

    public function update(Request $request, MealEntry $mealEntry, NutritionCalculator $calculator): RedirectResponse
    {
        $request->merge([
            'edit_mode' => $request->input('edit_mode', 'macros'),
        ]);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(MealEntry::MEAL_TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'edit_mode' => ['required', Rule::in(['portion', 'macros'])],
            'portion_quantity' => ['exclude_unless:edit_mode,portion', 'required', 'numeric', 'min:0.1', 'max:10000'],
            'portion_unit' => ['exclude_unless:edit_mode,portion', 'nullable', Rule::in(['g', 'ml'])],
            'protein_g' => ['exclude_unless:edit_mode,macros', 'required', 'numeric', 'min:0', 'max:1000'],
            'carbs_g' => ['exclude_unless:edit_mode,macros', 'required', 'numeric', 'min:0', 'max:1000'],
            'fat_g' => ['exclude_unless:edit_mode,macros', 'required', 'numeric', 'min:0', 'max:1000'],
        ]);

        $mealEntry->fill([
            'date' => $validated['date'],
            'meal_type' => $validated['meal_type'],
            'name' => $validated['name'],
        ]);

        if ($validated['edit_mode'] === 'portion') {
            $portionUnit = $validated['portion_unit'] ?? null;

            if ((float) $validated['portion_quantity'] !== (float) $mealEntry->portion_quantity || $portionUnit !== $mealEntry->portion_unit) {
                $this->applyPortion($mealEntry, $mealEntry, $validated['portion_quantity'], $portionUnit, $calculator);
            }
        } elseif ((float) $validated['protein_g'] !== (float) $mealEntry->protein_g
            || (float) $validated['carbs_g'] !== (float) $mealEntry->carbs_g
            || (float) $validated['fat_g'] !== (float) $mealEntry->fat_g) {
            $mealEntry->fill([
                'protein_g' => $validated['protein_g'],
                'carbs_g' => $validated['carbs_g'],
                'fat_g' => $validated['fat_g'],
                'calories' => $calculator->macroCalories($validated['protein_g'], $validated['carbs_g'], $validated['fat_g']),
            ]);
        }

        $mealEntry->save();

        $this->analytics->record('meal_updated');

        return redirect('/?date='.$validated['date'])->with('message', 'Meal updated.');
    }

    public function repeat(Request $request, MealEntry $mealEntry, NutritionCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['nullable', Rule::in(MealEntry::MEAL_TYPES)],
            'portion_quantity' => ['nullable', 'required_with:portion_unit', 'numeric', 'min:0.1', 'max:10000'],
            'portion_unit' => ['nullable', Rule::requiredIf(fn (): bool => $request->filled('portion_quantity') && $mealEntry->portion_unit !== null), Rule::in(['g', 'ml'])],
            'quick_log' => ['sometimes', 'boolean'],
        ]);

        $copy = $mealEntry->replicate([
            'id',
            'date',
            'meal_type',
            'created_at',
            'updated_at',
        ]);

        $copy->date = Carbon::parse($validated['date'])->toDateString();
        $copy->meal_type = $validated['meal_type'] ?? $mealEntry->meal_type;

        if (isset($validated['portion_quantity'])) {
            $this->applyPortion($copy, $mealEntry, $validated['portion_quantity'], $validated['portion_unit'] ?? null, $calculator);
        }

        $copy->save();

        $this->analytics->record('meal_repeated');

        return $this->loggedMeal($copy, 'Meal added again.', $request->boolean('quick_log'));
    }

    private function loggedMeal(MealEntry $meal, string $message, bool $quickLog = false): RedirectResponse
    {
        $date = $meal->date->toDateString();
        $response = redirect('/?date='.$date);

        if (MealEntry::query()->whereKeyNot($meal->getKey())->doesntExist()) {
            $response->with('offer_meal_reminder', true);
        }

        return $quickLog
            ? $response->with('quick_meal_log', ['id' => $meal->id, 'name' => $meal->name, 'date' => $date])
            : $response->with('message', $message);
    }

    public function destroy(MealEntry $mealEntry): RedirectResponse
    {
        $date = $mealEntry->date->toDateString();

        $mealEntry->delete();

        $this->analytics->record('meal_deleted');

        return redirect('/?date='.$date)->with('message', 'Meal removed.');
    }

    private function productPayload(FoodProduct $product): array
    {
        return [
            'type' => 'product',
            'id' => $product->id,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'brand' => $product->brand,
            'image_url' => $product->image_url,
            'nutrition_unit' => $product->nutrition_unit,
            'calories_per_100' => (float) $product->calories_per_100,
            'protein_per_100' => (float) $product->protein_per_100,
            'carbs_per_100' => (float) $product->carbs_per_100,
            'fat_per_100' => (float) $product->fat_per_100,
        ];
    }

    private function applyPortion(MealEntry $target, MealEntry $source, float|int|string $quantity, ?string $unit, NutritionCalculator $calculator): void
    {
        if ($source->foodProduct) {
            if ($unit !== $source->foodProduct->nutrition_unit) {
                throw ValidationException::withMessages([
                    'portion_unit' => "Use {$source->foodProduct->nutrition_unit} for this product because its nutrition data is per 100{$source->foodProduct->nutrition_unit}.",
                ]);
            }

            $target->fill([
                'portion_quantity' => $quantity,
                'portion_unit' => $unit,
                ...$calculator->macrosForPortion($source->foodProduct, $quantity),
            ]);

            return;
        }

        if ((float) $source->portion_quantity <= 0 || $source->portion_unit !== $unit) {
            throw ValidationException::withMessages([
                'portion_unit' => 'Use the original portion unit for this saved meal.',
            ]);
        }

        $factor = (float) $quantity / (float) $source->portion_quantity;

        $target->fill([
            'portion_quantity' => $quantity,
            'portion_unit' => $unit,
            'calories' => (int) round((float) $source->calories * $factor),
            'protein_g' => round((float) $source->protein_g * $factor, 2),
            'carbs_g' => round((float) $source->carbs_g * $factor, 2),
            'fat_g' => round((float) $source->fat_g * $factor, 2),
        ]);
    }

    private function previousMealsForSearch(string $query): Collection
    {
        return $this->previousFoodEntryQuery()
            ->where('name', 'like', "%{$query}%")
            ->latest()
            ->limit(100)
            ->get()
            ->unique(fn (MealEntry $entry): string => $this->previousFoodEntryKey($entry))
            ->take(8)
            ->map(fn (MealEntry $entry): array => $this->previousFoodEntryPayload($entry))
            ->values();
    }

    private function previousFoodEntries(): array
    {
        return $this->previousFoodEntryQuery()
            ->latest()
            ->limit(200)
            ->get()
            ->unique(fn (MealEntry $entry): string => $this->previousFoodEntryKey($entry))
            ->take(12)
            ->map(fn (MealEntry $entry): array => $this->previousFoodEntryPayload($entry))
            ->values()
            ->all();
    }

    private function previousFoodEntryQuery(): Builder
    {
        return MealEntry::query()
            ->with('foodProduct')
            ->whereIn('source_type', [MealEntry::SOURCE_CUSTOM, MealEntry::SOURCE_BARCODE, MealEntry::SOURCE_RECIPE]);
    }

    private function previousFoodEntryKey(MealEntry $entry): string
    {
        return implode('|', [
            $entry->source_type,
            (string) $entry->food_product_id,
            mb_strtolower($entry->name),
            (float) $entry->portion_quantity,
            (string) $entry->portion_unit,
            $entry->calories,
            (float) $entry->protein_g,
            (float) $entry->carbs_g,
            (float) $entry->fat_g,
        ]);
    }

    private function previousFoodEntryPayload(MealEntry $entry): array
    {
        return [
            'type' => 'previous_meal',
            'id' => $entry->id,
            'source_type' => $entry->source_type,
            'name' => $entry->name,
            'brand' => $entry->foodProduct?->brand ?: 'Previous item',
            'image_url' => $entry->foodProduct?->image_url,
            'portion_quantity' => $entry->portion_quantity !== null ? (float) $entry->portion_quantity : null,
            'portion_unit' => $entry->portion_unit,
            'calories' => $entry->calories,
            'protein_g' => (float) $entry->protein_g,
            'carbs_g' => (float) $entry->carbs_g,
            'fat_g' => (float) $entry->fat_g,
            'last_used_at' => $entry->created_at?->toDateTimeString(),
        ];
    }

    private function previousCustomMeals(): array
    {
        return MealEntry::query()
            ->where('source_type', MealEntry::SOURCE_CUSTOM)
            ->latest()
            ->limit(200)
            ->get()
            ->unique(fn (MealEntry $entry): string => implode('|', [
                mb_strtolower($entry->name),
                (float) $entry->portion_quantity,
                (string) $entry->portion_unit,
                $entry->calories,
                (float) $entry->protein_g,
                (float) $entry->carbs_g,
                (float) $entry->fat_g,
            ]))
            ->take(10)
            ->map(fn (MealEntry $entry): array => [
                'id' => $entry->id,
                'name' => $entry->name,
                'portion_quantity' => $entry->portion_quantity !== null ? (float) $entry->portion_quantity : null,
                'portion_unit' => $entry->portion_unit,
                'calories' => $entry->calories,
                'protein_g' => (float) $entry->protein_g,
                'carbs_g' => (float) $entry->carbs_g,
                'fat_g' => (float) $entry->fat_g,
                'last_used_at' => $entry->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }
}
