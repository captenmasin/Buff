<?php

use App\Models\FoodProduct;
use App\Models\MealEntry;
use App\Services\BuffCredentialStore;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app(BuffCredentialStore::class)->store('food-token', [
        'id' => '10000000-0000-4000-8000-000000000001',
        'email_verified' => true,
    ]);
});

it('fetches and stores a normalized barcode product from buff-server', function (): void {
    Http::fake(['*/foods/barcodes/737628064502' => Http::response([
        'product' => productPayload(),
        'portion_options' => [],
    ])]);

    $this->postJson('/barcode/lookup', ['barcode' => '737628064502'])
        ->assertOk()
        ->assertJsonPath('product.name', 'Example Bar')
        ->assertJsonPath('portion_options.0.quantity', 38)
        ->assertJsonPath('portion_options.1.quantity', 152);

    $this->assertDatabaseHas('food_products', [
        'id' => '20000000-0000-4000-8000-000000000002',
        'barcode' => '737628064502',
        'name' => 'Example Bar',
    ]);
});

it('removes whitespace before requesting the barcode proxy', function (): void {
    Http::fake(['*/foods/barcodes/737628064502' => Http::response([
        'product' => productPayload(),
        'portion_options' => [],
    ])]);

    $this->postJson('/barcode/lookup', ['barcode' => "737 628 064 502 \n"])
        ->assertOk()
        ->assertJsonPath('product.barcode', '737628064502');

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api.usebuff.app/api/v1/foods/barcodes/737628064502'
        && $request->hasHeader('Authorization', 'Bearer food-token'));
});

it('keeps server-normalized millilitre portions', function (): void {
    Http::fake(['*/foods/barcodes/5000181036312' => Http::response([
        'product' => productPayload([
            'id' => '30000000-0000-4000-8000-000000000003',
            'barcode' => '5000181036312',
            'name' => 'Arla B.O.B',
            'serving_label' => '200ml',
            'serving_quantity' => 200,
            'serving_unit' => 'ml',
            'package_label' => '2l',
            'package_quantity' => 2000,
            'package_unit' => 'ml',
            'nutrition_unit' => 'ml',
        ]),
        'portion_options' => [],
    ])]);

    $this->postJson('/barcode/lookup', ['barcode' => '5000181036312'])
        ->assertOk()
        ->assertJsonPath('product.nutrition_unit', 'ml')
        ->assertJsonPath('portion_options.0.label', '1 serving (200ml)')
        ->assertJsonPath('portion_options.1.label', 'Whole thing (2l)')
        ->assertJsonPath('portion_options.2.label', '100ml');
});

it('searches through the buff-server proxy and stores returned products', function (): void {
    Http::fake(['*/foods/search*' => Http::response(['products' => [productPayload()]])]);

    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->getJson('/food-products/search?q=example')
        ->assertOk()
        ->assertJsonPath('products.0.name', 'Example Bar');

    $this->assertDatabaseHas('food_products', ['barcode' => '737628064502']);
    Http::assertSent(fn (ClientRequest $request): bool => $request['locale'] === 'fr_FR');
});

it('reuses a stored product when the server returns a new id for its barcode', function (): void {
    $storedProduct = FoodProduct::query()->create(productPayload([
        'id' => '10000000-0000-4000-8000-000000000001',
        'name' => 'Old product name',
    ]));
    Http::fake(['*/foods/search*' => Http::response(['products' => [productPayload()]])]);

    $this->getJson('/food-products/search?q=example')
        ->assertOk()
        ->assertJsonPath('products.0.id', $storedProduct->id)
        ->assertJsonPath('products.0.name', 'Example Bar');

    expect(FoodProduct::query()->count())->toBe(1);
    $this->assertDatabaseMissing('food_products', ['id' => '20000000-0000-4000-8000-000000000002']);
});

it('uses stored products when the remote search is unavailable', function (): void {
    Http::fake(['*/foods/search*' => Http::response(['products' => [productPayload()]])]);
    $this->getJson('/food-products/search?q=example')->assertOk();
    $product = FoodProduct::query()->where('barcode', '737628064502')->firstOrFail();
    MealEntry::query()->create([
        'date' => '2026-08-27',
        'meal_type' => 'snacks',
        'source_type' => MealEntry::SOURCE_BARCODE,
        'food_product_id' => $product->id,
        'name' => $product->name,
        'calories' => 420,
        'protein_g' => 20,
        'carbs_g' => 48,
        'fat_g' => 12,
    ]);

    Http::fake(['*/foods/search*' => Http::failedConnection()]);

    $this->getJson('/food-products/search?q=example')
        ->assertOk()
        ->assertJsonPath('products.0.name', 'Example Bar');
});

it('does not reuse unselected search results when the remote search is unavailable', function (): void {
    Http::fake(['*/foods/search*' => Http::response(['products' => [productPayload()]])]);
    $this->getJson('/food-products/search?q=example')->assertOk();

    Http::fake(['*/foods/search*' => Http::failedConnection()]);

    $this->getJson('/food-products/search?q=example')
        ->assertOk()
        ->assertJsonCount(0, 'products');
});

/** @param array<string, mixed> $overrides */
function productPayload(array $overrides = []): array
{
    return [...[
        'id' => '20000000-0000-4000-8000-000000000002',
        'barcode' => '737628064502',
        'name' => 'Example Bar',
        'brand' => 'Buff Foods',
        'image_url' => 'https://example.com/bar.jpg',
        'serving_label' => '38g',
        'serving_quantity' => 38,
        'serving_unit' => 'g',
        'package_label' => '152g',
        'package_quantity' => 152,
        'package_unit' => 'g',
        'nutrition_unit' => 'g',
        'calories_per_100' => 420,
        'protein_per_100' => 20,
        'carbs_per_100' => 48,
        'fat_per_100' => 12,
    ], ...$overrides];
}
