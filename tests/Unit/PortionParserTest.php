<?php

namespace Tests\Unit;

use App\Models\FoodProduct;
use App\Services\PortionParser;
use PHPUnit\Framework\TestCase;

class PortionParserTest extends TestCase
{
    public function test_it_parses_grams_and_millilitres(): void
    {
        $parser = new PortionParser;

        $this->assertSame(['quantity' => 38.0, 'unit' => 'g', 'label' => '38g'], $parser->parse('38g'));
        $this->assertSame(['quantity' => 330.0, 'unit' => 'ml', 'label' => '330 ml'], $parser->parse('330 ml'));
        $this->assertSame(['quantity' => 2000.0, 'unit' => 'ml', 'label' => '2l'], $parser->parse('2l'));
        $this->assertNull($parser->parse('one pack'));
    }

    public function test_it_parses_structured_quantities(): void
    {
        $parser = new PortionParser;

        $this->assertSame(['quantity' => 2000.0, 'unit' => 'ml', 'label' => '2l'], $parser->parseQuantity(2, 'l', '2l'));
        $this->assertSame(['quantity' => 200.0, 'unit' => 'g', 'label' => '200g'], $parser->parseQuantity(200, 'g'));
        $this->assertNull($parser->parseQuantity(200, 'portion'));
    }

    public function test_it_builds_common_options_with_serving_and_package_first(): void
    {
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

        $this->assertSame('1 serving (30g)', $options[0]['label']);
        $this->assertSame('Whole thing (300g)', $options[1]['label']);
        $this->assertContains('100g', array_column($options, 'label'));
    }
}
