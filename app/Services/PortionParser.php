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

        if (! preg_match('/(\d+(?:\.\d+)?)\s*(g|gram|grams|ml|milliliter|milliliters|millilitre|millilitres|l|liter|liters|litre|litres)\b/', $normalized, $matches)) {
            return null;
        }

        return $this->parseQuantity($matches[1], $matches[2], trim($value));
    }

    public function parseQuantity(float|int|string|null $quantity, ?string $unit, ?string $label = null): ?array
    {
        if ($quantity === null || ! is_numeric($quantity)) {
            return null;
        }

        $unit = $this->normalizeUnit($unit);

        if ($unit === null) {
            return null;
        }

        $quantity = round((float) $quantity, 2);

        if ($unit === 'l') {
            $quantity *= 1000;
            $unit = 'ml';
        }

        if ($quantity <= 0) {
            return null;
        }

        return [
            'quantity' => $quantity,
            'unit' => $unit,
            'label' => $label !== null && trim($label) !== '' ? trim($label) : $this->formatQuantity($quantity, $unit),
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
                'label' => 'Whole thing'.($product->package_label ? " ({$product->package_label})" : ''),
                'quantity' => (float) $product->package_quantity,
                'unit' => $product->package_unit,
            ];
        }

        $defaultUnit = $product->nutrition_unit
            ?? $product->serving_unit
            ?? $product->package_unit
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

    public function formatQuantity(float|int|string $quantity, string $unit): string
    {
        $quantity = (float) $quantity;
        $formatted = fmod($quantity, 1.0) === 0.0
            ? (string) (int) $quantity
            : rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');

        return "{$formatted}{$unit}";
    }

    private function normalizeUnit(?string $unit): ?string
    {
        $unit = strtolower(trim((string) $unit));

        return match ($unit) {
            'g', 'gram', 'grams' => 'g',
            'ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres' => 'ml',
            'l', 'liter', 'liters', 'litre', 'litres' => 'l',
            default => null,
        };
    }
}
