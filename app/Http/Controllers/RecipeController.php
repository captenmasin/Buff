<?php

namespace App\Http\Controllers;

use App\Models\FoodProduct;
use App\Models\Recipe;
use App\Services\NutritionCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class RecipeController extends Controller
{
    public function store(Request $request, NutritionCalculator $calculator): RedirectResponse
    {
        $recipe = Recipe::query()->create($this->validatedRecipe($request, $calculator));
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())->toDateString()
            : today()->toDateString();

        return redirect('/add?mode=recipe&date='.$date)
            ->with('message', "{$recipe->name} saved.");
    }

    public function update(Request $request, Recipe $recipe, NutritionCalculator $calculator): RedirectResponse
    {
        $recipe->update($this->validatedRecipe($request, $calculator));

        return back()->with('message', 'Recipe updated.');
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $recipe->delete();

        return back()->with('message', 'Recipe deleted.');
    }

    /**
     * @return array{name: string, servings: float, items: list<array{name: string, food_product_id: string|null, portion_quantity: float, portion_unit: string, calories: int, protein_g: float, carbs_g: float, fat_g: float}>}
     */
    private function validatedRecipe(Request $request, NutritionCalculator $calculator): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'servings' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.food_product_id' => ['nullable', 'uuid', 'exists:food_products,id'],
            'items.*.portion_quantity' => ['required', 'numeric', 'min:0.1', 'max:10000'],
            'items.*.portion_unit' => ['required', Rule::in(['g', 'ml'])],
            'items.*.calories' => ['required', 'integer', 'min:0', 'max:20000'],
            'items.*.protein_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'items.*.carbs_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'items.*.fat_g' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        $items = collect($validated['items'])
            ->map(function (array $item) use ($calculator): array {
                if (is_string($item['food_product_id'] ?? null)) {
                    $product = FoodProduct::query()->findOrFail($item['food_product_id']);
                    $macros = $calculator->macrosForPortion($product, $item['portion_quantity']);
                    $item = [
                        ...$item,
                        ...$macros,
                        'name' => $item['name'] !== '' ? $item['name'] : $product->name,
                    ];
                }

                return [
                    'name' => $item['name'],
                    'food_product_id' => $item['food_product_id'] ?? null,
                    'portion_quantity' => (float) $item['portion_quantity'],
                    'portion_unit' => $item['portion_unit'],
                    'calories' => (int) $item['calories'],
                    'protein_g' => round((float) $item['protein_g'], 2),
                    'carbs_g' => round((float) $item['carbs_g'], 2),
                    'fat_g' => round((float) $item['fat_g'], 2),
                ];
            })
            ->values()
            ->all();

        return [
            'name' => $validated['name'],
            'servings' => $validated['servings'],
            'items' => $items,
        ];
    }
}
