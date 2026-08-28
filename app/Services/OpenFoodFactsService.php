<?php

namespace App\Services;

use App\BuffApiStatus;
use App\Models\FoodProduct;
use Illuminate\Validation\ValidationException;

class OpenFoodFactsService
{
    public function __construct(private readonly BuffApiClient $api) {}

    public function lookup(string $barcode): FoodProduct
    {
        $barcode = preg_replace('/\D+/', '', $barcode) ?? '';

        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'Enter a valid barcode.']);
        }

        $result = $this->api->get("foods/barcodes/{$barcode}");

        if ($result->successful() && is_array($result->data['product'] ?? null)) {
            return $this->storeProduct($result->data['product']);
        }

        $cached = FoodProduct::query()->where('barcode', $barcode)->first();

        if ($cached !== null) {
            return $cached;
        }

        $message = match ($result->status) {
            BuffApiStatus::Unauthenticated => 'Sign in to look up new foods.',
            BuffApiStatus::ConnectionFailed => 'You are offline and this barcode is not stored on this device.',
            default => $result->errors['barcode'][0] ?? $result->message ?? 'Buff could not find that barcode.',
        };

        throw ValidationException::withMessages(['barcode' => $message]);
    }

    /** @return array<int, FoodProduct> */
    public function search(string $query, int $limit = 20, string $locale = 'en_GB'): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $result = $this->api->get('foods/search', ['q' => $query, 'locale' => $locale]);

        if (! $result->successful() || ! is_array($result->data['products'] ?? null)) {
            return [];
        }

        return collect($result->data['products'])
            ->take(min(max($limit, 1), 50))
            ->filter(fn (mixed $product): bool => is_array($product))
            ->map(fn (array $product): FoodProduct => $this->storeProduct($product))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data */
    private function storeProduct(array $data): FoodProduct
    {
        if (! is_string($data['id'] ?? null) || ! is_string($data['barcode'] ?? null) || ! is_string($data['name'] ?? null)) {
            throw ValidationException::withMessages(['barcode' => 'Buff returned an invalid food product.']);
        }

        $product = FoodProduct::query()->where('barcode', $data['barcode'])->first()
            ?? FoodProduct::query()->find($data['id'])
            ?? new FoodProduct;

        if (! $product->exists) {
            $product->setAttribute('id', $data['id']);
        }

        $product->forceFill(collect($data)->only([
            'barcode',
            'name',
            'brand',
            'image_url',
            'serving_label',
            'serving_quantity',
            'serving_unit',
            'package_label',
            'package_quantity',
            'package_unit',
            'nutrition_unit',
            'calories_per_100',
            'protein_per_100',
            'carbs_per_100',
            'fat_per_100',
        ])->all());
        $product->save();

        return $product;
    }
}
