<?php

namespace App\Http\Controllers;

use App\Models\FoodProduct;
use App\Models\MealEntry;
use App\Models\PendingMealAnalysisConfirmation;
use App\Models\Recipe;
use App\Services\BuffSyncService;
use App\Services\NutritionCalculator;
use App\Services\OpenFoodFactsService;
use App\Services\PortionParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MealController extends Controller
{
    public function create(Request $request): Response
    {
        $validated = $request->validate(['date' => ['nullable', 'date']]);
        $mode = $request->string('mode')->toString();
        $mode = match ($mode) {
            'barcode', 'search' => 'food',
            default => $mode,
        };

        $meal = $request->string('meal')->toString();
        $availableModes = ['food', 'custom', 'workout', 'photo', 'recipe'];

        return Inertia::render('Add', [
            'date' => isset($validated['date'])
                ? Carbon::parse($validated['date'])->toDateString()
                : today()->toDateString(),
            'mealTypes' => MealEntry::MEAL_TYPES,
            'meal' => $meal,
            'mode' => in_array($mode, $availableModes, true) ? $mode : 'choose',
            'autoScan' => $request->boolean('scan'),
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
        $request->merge([
            'barcode' => preg_replace('/\s+/', '', (string) $request->input('barcode', '')) ?? '',
        ]);

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:32'],
        ]);

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

        $remoteProducts = $openFoodFacts->search($query, 20, $request->getPreferredLanguage() ?? 'en_GB');

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
            'portion_unit' => ['required', Rule::in(['g', 'ml'])],
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
            'calories' => $calculator->macroCalories($validated['protein_g'], $validated['carbs_g'], $validated['fat_g']),
        ]);

        if (is_string($analysisId)) {
            PendingMealAnalysisConfirmation::query()->updateOrCreate(
                ['analysis_id' => $analysisId],
                ['meal_record_id' => $meal->id, 'last_error' => null],
            );

            defer(fn () => $sync->sync(), 'buff-sync');
        }

        return redirect('/?date='.$validated['date'])->with(
            'message',
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
        ]);

        $product = FoodProduct::query()->findOrFail($validated['food_product_id']);

        if ($validated['portion_unit'] !== $product->nutrition_unit) {
            throw ValidationException::withMessages([
                'portion_unit' => "Use {$product->nutrition_unit} for this product because its nutrition data is per 100{$product->nutrition_unit}.",
            ]);
        }

        MealEntry::query()->create([
            'date' => $validated['date'],
            'meal_type' => $validated['meal_type'],
            'source_type' => MealEntry::SOURCE_BARCODE,
            'food_product_id' => $product->id,
            'name' => $product->name,
            'portion_quantity' => $validated['portion_quantity'],
            'portion_unit' => $validated['portion_unit'],
            ...$calculator->macrosForPortion($product, $validated['portion_quantity']),
        ]);

        return redirect('/?date='.$validated['date'])->with('message', 'Meal added.');
    }

    public function storeFromRecipe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(MealEntry::MEAL_TYPES)],
            'recipe_id' => ['required', 'uuid', 'exists:recipes,id'],
            'servings' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
        ]);

        $recipe = Recipe::query()->findOrFail($validated['recipe_id']);
        $loggedServings = (float) ($validated['servings'] ?? $recipe->servings);
        $factor = $loggedServings / max((float) $recipe->servings, 0.1);
        $totals = $recipe->totals();

        MealEntry::query()->create([
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

        return redirect('/?date='.$validated['date'])->with('message', 'Recipe logged.');
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
            $this->applyPortion($mealEntry, $mealEntry, $validated['portion_quantity'], $validated['portion_unit'], $calculator);
        } else {
            $mealEntry->fill([
                'protein_g' => $validated['protein_g'],
                'carbs_g' => $validated['carbs_g'],
                'fat_g' => $validated['fat_g'],
                'calories' => $calculator->macroCalories($validated['protein_g'], $validated['carbs_g'], $validated['fat_g']),
            ]);
        }

        $mealEntry->save();

        return redirect('/?date='.$validated['date'])->with('message', 'Meal updated.');
    }

    public function repeat(Request $request, MealEntry $mealEntry, NutritionCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['nullable', Rule::in(MealEntry::MEAL_TYPES)],
            'portion_quantity' => ['nullable', 'required_with:portion_unit', 'numeric', 'min:0.1', 'max:10000'],
            'portion_unit' => ['nullable', 'required_with:portion_quantity', Rule::in(['g', 'ml'])],
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

        if (array_key_exists('portion_quantity', $validated) && array_key_exists('portion_unit', $validated)) {
            $this->applyPortion($copy, $mealEntry, $validated['portion_quantity'], $validated['portion_unit'], $calculator);
        }

        $copy->save();

        return redirect('/?date='.$copy->date->toDateString())->with('message', 'Meal added again.');
    }

    public function destroy(MealEntry $mealEntry): RedirectResponse
    {
        $date = $mealEntry->date->toDateString();

        $mealEntry->delete();

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

        if ($source->portion_quantity === null || $source->portion_unit !== $unit) {
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
            ->whereIn('source_type', [MealEntry::SOURCE_CUSTOM, MealEntry::SOURCE_BARCODE]);
    }

    private function previousFoodEntryKey(MealEntry $entry): string
    {
        return implode('|', [
            $entry->source_type,
            (string) $entry->food_product_id,
            mb_strtolower($entry->name),
            (float) $entry->portion_quantity,
            (string) $entry->portion_unit,
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
