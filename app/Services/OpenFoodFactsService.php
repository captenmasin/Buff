<?php

namespace App\Services;

use App\Models\FoodProduct;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenFoodFactsService
{
    public function __construct(private readonly PortionParser $portionParser) {}

    /**
     * @throws RequestException
     * @throws ValidationException
     */
    public function lookup(string $barcode): FoodProduct
    {
        $barcode = preg_replace('/\D+/', '', $barcode) ?? '';

        if ($barcode === '') {
            throw ValidationException::withMessages([
                'barcode' => 'Enter a valid barcode.',
            ]);
        }

        $response = Http::withHeaders([
            'User-Agent' => 'BuffCalorieTracker/1.0 (local NativePHP app)',
        ])->timeout(10)->get("https://world.openfoodfacts.org/api/v2/product/{$barcode}.json", [
            'fields' => implode(',', [
                'code',
                'product_name',
                'brands',
                'image_url',
                'serving_size',
                'serving_quantity',
                'serving_quantity_unit',
                'quantity',
                'product_quantity',
                'product_quantity_unit',
                'nutrition_data_per',
                'nutriments',
            ]),
        ])->throw();

        $payload = $response->json();

        if (($payload['status'] ?? 0) !== 1 || ! isset($payload['product'])) {
            throw ValidationException::withMessages([
                'barcode' => 'Open Food Facts could not find that barcode.',
            ]);
        }

        return $this->storeProduct($barcode, $payload['product'], $payload);
    }

    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'BuffCalorieTracker/1.0 (local NativePHP app)',
            ])->timeout(10)->get('https://world.openfoodfacts.org/cgi/search.pl', [
                'search_terms' => $query,
                'search_simple' => 1,
                'action' => 'process',
                'json' => 1,
                'page_size' => $limit,
                'fields' => implode(',', [
                    'code',
                    'product_name',
                    'brands',
                    'image_url',
                    'serving_size',
                    'serving_quantity',
                    'serving_quantity_unit',
                    'quantity',
                    'product_quantity',
                    'product_quantity_unit',
                    'nutrition_data_per',
                    'nutriments',
                ]),
            ])->throw();
        } catch (ConnectionException|RequestException) {
            return [];
        }

        return collect($response->json('products', []))
            ->map(function (array $product): ?FoodProduct {
                $barcode = preg_replace('/\D+/', '', (string) ($product['code'] ?? '')) ?? '';

                if ($barcode === '' || ! filled($product['product_name'] ?? null)) {
                    return null;
                }

                try {
                    return $this->storeProduct($barcode, $product, ['product' => $product, 'status' => 1]);
                } catch (ValidationException) {
                    return null;
                }
            })
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }

    public function storeProduct(string $barcode, array $product, array $payload = []): FoodProduct
    {
        $nutriments = $product['nutriments'] ?? [];

        $protein = $this->nutriment($nutriments, 'proteins_100g');
        $carbs = $this->nutriment($nutriments, 'carbohydrates_100g');
        $fat = $this->nutriment($nutriments, 'fat_100g');
        $calories = $this->nutriment($nutriments, 'energy-kcal_100g')
            ?? $this->nutriment($nutriments, 'energy-kcal_value')
            ?? ($protein !== null && $carbs !== null && $fat !== null
                ? ((float) $protein * 4) + ((float) $carbs * 4) + ((float) $fat * 9)
                : null);

        if ($protein === null || $carbs === null || $fat === null || $calories === null) {
            throw ValidationException::withMessages([
                'barcode' => 'This product is missing usable macro nutrition data. Add it as a custom meal instead.',
            ]);
        }

        $serving = $this->portionParser->parseQuantity(
            $product['serving_quantity'] ?? null,
            $product['serving_quantity_unit'] ?? null,
            $product['serving_size'] ?? null,
        ) ?? $this->portionParser->parse($product['serving_size'] ?? null);

        $package = $this->portionParser->parseQuantity(
            $product['product_quantity'] ?? null,
            $product['product_quantity_unit'] ?? null,
            $product['quantity'] ?? null,
        ) ?? $this->portionParser->parse($product['quantity'] ?? null);

        $nutritionUnit = $this->nutritionUnit($product, $serving, $package);
        $serving = $this->normalizePortionUnit($serving, $nutritionUnit);
        $package = $this->normalizePortionUnit($package, $nutritionUnit);

        return FoodProduct::query()->updateOrCreate(
            ['barcode' => $barcode],
            [
                'name' => filled($product['product_name'] ?? null) ? Str::limit($product['product_name'], 255, '') : "Barcode {$barcode}",
                'brand' => filled($product['brands'] ?? null) ? Str::limit($product['brands'], 255, '') : null,
                'image_url' => $product['image_url'] ?? null,
                'serving_label' => $serving['label'] ?? null,
                'serving_quantity' => $serving['quantity'] ?? null,
                'serving_unit' => $serving['unit'] ?? null,
                'package_label' => $package['label'] ?? null,
                'package_quantity' => $package['quantity'] ?? null,
                'package_unit' => $package['unit'] ?? null,
                'nutrition_unit' => $nutritionUnit,
                'calories_per_100' => round((float) $calories, 2),
                'protein_per_100' => round((float) $protein, 2),
                'carbs_per_100' => round((float) $carbs, 2),
                'fat_per_100' => round((float) $fat, 2),
                'raw_payload' => $payload ?: $product,
                'fetched_at' => now(),
            ]
        );
    }

    private function nutriment(array $nutriments, string $key): ?float
    {
        if (! array_key_exists($key, $nutriments) || ! is_numeric($nutriments[$key])) {
            return null;
        }

        return (float) $nutriments[$key];
    }

    private function nutritionUnit(array $product, ?array $serving, ?array $package): string
    {
        $nutritionDataPer = strtolower((string) ($product['nutrition_data_per'] ?? ''));

        if (str_contains($nutritionDataPer, '100ml')) {
            return 'ml';
        }

        if (($package['unit'] ?? null) === 'ml' || ($serving['unit'] ?? null) === 'ml') {
            return 'ml';
        }

        return 'g';
    }

    private function normalizePortionUnit(?array $portion, string $nutritionUnit): ?array
    {
        if ($portion === null || ($portion['unit'] ?? null) === $nutritionUnit) {
            return $portion;
        }

        if ($nutritionUnit === 'ml' && ($portion['unit'] ?? null) === 'g') {
            $portion['unit'] = 'ml';
            $portion['label'] = $this->portionParser->formatQuantity($portion['quantity'], 'ml');
        }

        return $portion;
    }
}
