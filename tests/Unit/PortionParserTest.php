<?php

use App\Models\FoodProduct;
use App\Services\PortionParser;

it('parses grams and millilitres', function (): void {
    $parser = new PortionParser;

    expect($parser->parse('38g'))->toBe(['quantity' => 38.0, 'unit' => 'g', 'label' => '38g'])
        ->and($parser->parse('330 ml'))->toBe(['quantity' => 330.0, 'unit' => 'ml', 'label' => '330 ml'])
        ->and($parser->parse('2l'))->toBe(['quantity' => 2000.0, 'unit' => 'ml', 'label' => '2l'])
        ->and($parser->parse('one pack'))->toBeNull();
});

it('parses structured quantities', function (): void {
    $parser = new PortionParser;

    expect($parser->parseQuantity(2, 'l', '2l'))->toBe(['quantity' => 2000.0, 'unit' => 'ml', 'label' => '2l'])
        ->and($parser->parseQuantity(200, 'g'))->toBe(['quantity' => 200.0, 'unit' => 'g', 'label' => '200g'])
        ->and($parser->parseQuantity(200, 'portion'))->toBeNull();
});

it('builds common options with serving and package first', function (): void {
    $parser = new PortionParser;
    $product = new FoodProduct([
        'serving_label' => '30g',
        'serving_quantity' => 30,
        'serving_unit' => 'g',
        'package_label' => '300g',
        'package_quantity' => 300,
        'package_unit' => 'g',
        'nutrition_unit' => 'g',
    ]);

    $options = $parser->optionsForProduct($product);

    expect($options[0]['label'])->toBe('1 serving (30g)')
        ->and($options[1]['label'])->toBe('Whole thing (300g)')
        ->and(array_column($options, 'label'))->toContain('100g');
});
