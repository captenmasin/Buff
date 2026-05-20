<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenFoodFactsLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fetches_and_normalizes_a_barcode_product(): void
    {
        Http::fake([
            'world.openfoodfacts.org/api/v2/product/737628064502.json*' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '737628064502',
                    'product_name' => 'Example Bar',
                    'brands' => 'Buff Foods',
                    'serving_size' => '38g',
                    'quantity' => '152g',
                    'image_url' => 'https://example.com/bar.jpg',
                    'nutriments' => [
                        'energy-kcal_100g' => 420,
                        'proteins_100g' => 20,
                        'carbohydrates_100g' => 48,
                        'fat_100g' => 12,
                    ],
                ],
            ]),
        ]);

        $this->postJson('/barcode/lookup', [
            'barcode' => '737628064502',
        ])->assertOk()
            ->assertJsonPath('product.name', 'Example Bar')
            ->assertJsonPath('portion_options.0.quantity', 38)
            ->assertJsonPath('portion_options.1.quantity', 152);

        $this->assertDatabaseHas('food_products', [
            'barcode' => '737628064502',
            'name' => 'Example Bar',
        ]);
    }

    public function test_it_uses_millilitres_for_liquid_packaged_products(): void
    {
        Http::fake([
            'world.openfoodfacts.org/api/v2/product/5000181036312.json*' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '5000181036312',
                    'product_name' => 'Arla B.O.B',
                    'brands' => 'Arla',
                    'serving_size' => '1 portion (200 g)',
                    'serving_quantity' => 200,
                    'serving_quantity_unit' => 'g',
                    'quantity' => '2l',
                    'product_quantity' => 2000,
                    'product_quantity_unit' => 'ml',
                    'nutrition_data_per' => '100g',
                    'nutriments' => [
                        'energy-kcal_100g' => 41.5,
                        'proteins_100g' => 4.55,
                        'carbohydrates_100g' => 4.9,
                        'fat_100g' => 0.4,
                    ],
                ],
            ]),
        ]);

        $this->postJson('/barcode/lookup', [
            'barcode' => '5000181036312',
        ])->assertOk()
            ->assertJsonPath('product.name', 'Arla B.O.B')
            ->assertJsonPath('product.nutrition_unit', 'ml')
            ->assertJsonPath('portion_options.0.label', '1 serving (200ml)')
            ->assertJsonPath('portion_options.0.unit', 'ml')
            ->assertJsonPath('portion_options.1.label', 'Whole thing (2l)')
            ->assertJsonPath('portion_options.1.unit', 'ml')
            ->assertJsonPath('portion_options.2.label', '100ml');

        $this->assertDatabaseHas('food_products', [
            'barcode' => '5000181036312',
            'nutrition_unit' => 'ml',
            'serving_unit' => 'ml',
            'package_unit' => 'ml',
        ]);
    }
}
