<?php

namespace App\Services;

use App\Models\FoodProduct;

class PortionParser
{
    public function parse(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(str_replace(',', '.', $value));

        if (! preg_match('/(\d+(?:\.\d+)?)\s*(g|gram|grams|ml|milliliter|milliliters|millilitre|millilitres)\b/', $normalized, $matches)) {
            return null;
        }

        $unit = str_starts_with($matches[2], 'm') ? 'ml' : 'g';
        $quantity = round((float) $matches[1], 2);

        if ($quantity <= 0) {
            return null;
        }

        return [
            'quantity' => $quantity,
            'unit' => $unit,
            'label' => trim($value),
        ];
    }

    public function optionsForProduct(FoodProduct $product): array
    {
        $options = [];

        if ($product->serving_quantity !== null && $product->serving_unit !== null) {
            $options[] = [
                'label' => '1 serving'.($product->serving_label ? " ({$product->serving_label})" : ''),
                'quantity' => (float) $product->serving_quantity,
                'unit' => $product->serving_unit,
            ];
        }

        if ($product->package_quantity !== null && $product->package_unit !== null) {
            $options[] = [
                'label' => 'Whole item'.($product->package_label ? " ({$product->package_label})" : ''),
                'quantity' => (float) $product->package_quantity,
                'unit' => $product->package_unit,
            ];
        }

        $defaultUnit = $product->serving_unit
            ?? $product->package_unit
            ?? $product->nutrition_unit
            ?? 'g';

        $defaults = $defaultUnit === 'ml' ? [100, 250, 330, 500] : [50, 100, 150, 250];

        foreach ($defaults as $quantity) {
            $options[] = [
                'label' => "{$quantity}{$defaultUnit}",
                'quantity' => $quantity,
                'unit' => $defaultUnit,
            ];
        }

        return collect($options)
            ->unique(fn (array $option): string => $option['quantity'].'-'.$option['unit'])
            ->values()
            ->all();
    }
}
