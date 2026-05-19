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
}
