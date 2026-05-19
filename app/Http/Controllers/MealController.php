<?php

namespace App\Http\Controllers;

use App\Models\FoodProduct;
use App\Models\MealEntry;
use App\Services\NutritionCalculator;
use App\Services\OpenFoodFactsService;
use App\Services\PortionParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MealController extends Controller
{
    public function create(Request $request): Response
    {
        $mode = $request->string('mode')->toString();

        return Inertia::render('Add', [
            'date' => $request->filled('date')
                ? Carbon::parse($request->string('date')->toString())->toDateString()
                : today()->toDateString(),
            'mealTypes' => MealEntry::MEAL_TYPES,
            'mode' => in_array($mode, ['barcode', 'custom', 'workout'], true) ? $mode : 'choose',
            'autoScan' => $request->boolean('scan'),
            'previousCustomMeals' => $this->previousCustomMeals(),
        ]);
    }

    public function lookupBarcode(Request $request, OpenFoodFactsService $openFoodFacts, PortionParser $portionParser): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:32'],
        ]);

        $product = $openFoodFacts->lookup($validated['barcode']);

        return response()->json([
            'product' => $this->productPayload($product),
            'portion_options' => $portionParser->optionsForProduct($product),
        ]);
    }

    public function storeCustom(Request $request, NutritionCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(MealEntry::MEAL_TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'carbs_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        MealEntry::query()->create([
            ...$validated,
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'calories' => $calculator->macroCalories($validated['protein_g'], $validated['carbs_g'], $validated['fat_g']),
        ]);

        return redirect('/?date='.$validated['date'])->with('message', 'Custom meal added.');
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

    public function destroy(MealEntry $mealEntry): RedirectResponse
    {
        $date = $mealEntry->date->toDateString();

        $mealEntry->delete();

        return redirect('/?date='.$date)->with('message', 'Meal removed.');
    }

    private function productPayload(FoodProduct $product): array
    {
        return [
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

    private function previousCustomMeals(): array
    {
        return MealEntry::query()
            ->where('source_type', MealEntry::SOURCE_CUSTOM)
            ->latest()
            ->limit(200)
            ->get()
            ->unique(fn (MealEntry $entry): string => implode('|', [
                mb_strtolower($entry->name),
                (float) $entry->protein_g,
                (float) $entry->carbs_g,
                (float) $entry->fat_g,
            ]))
            ->take(10)
            ->map(fn (MealEntry $entry): array => [
                'id' => $entry->id,
                'name' => $entry->name,
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
