<?php

use App\Models\Inventory\Brand;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('lists products with pagination', function () {
    $this->actingAs(User::factory()->create(), 'api');

    Product::factory()->count(16)->create();

    $this->getJson(route('v1.inventory.products.index', ['page' => 2]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 16);
});

test('creates a product with its price in whole kyat', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    $response = $this->postJson(route('v1.inventory.products.store'), [
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Keyboard',
        'sku' => 'KB-001',
        'selling_price' => 15000,
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.selling_price', 15000)
        ->assertJsonPath('data.stock_quantity', 0)
        ->assertJsonPath('data.type', 'in_stock')
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.brand.id', $brand->id);
    $this->assertDatabaseHas(Product::class, ['sku' => 'KB-001', 'selling_price' => 15000]);
});

test('shows a product', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['selling_price' => 15000]);

    $this->getJson(route('v1.inventory.products.show', $product))
        ->assertOk()->assertJsonPath('data.id', $product->id)->assertJsonPath('data.selling_price', 15000);
});

test('updates a product', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create();

    $this->patchJson(route('v1.inventory.products.update', $product), [
        'selling_price' => 20000,
        'stock_quantity' => 10,
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.selling_price', 20000)->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'selling_price' => 20000, 'stock_quantity' => 10]);
});

test('deletes a product that has no invoices', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create();

    $this->deleteJson(route('v1.inventory.products.destroy', $product))->assertNoContent();

    $this->assertModelMissing($product);
});

test('requires product details', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->postJson(route('v1.inventory.products.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id', 'brand_id', 'name', 'sku', 'selling_price', 'is_active']);

    $this->assertDatabaseEmpty(Product::class);
});

test('rejects decimal and negative product prices', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['selling_price' => 15000]);

    $this->patchJson(route('v1.inventory.products.update', $product), ['selling_price' => 15000.5])
        ->assertUnprocessable()->assertJsonValidationErrors('selling_price');
    $this->patchJson(route('v1.inventory.products.update', $product), ['selling_price' => -1])
        ->assertUnprocessable()->assertJsonValidationErrors('selling_price');

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'selling_price' => 15000]);
});

test('rejects duplicate product SKUs', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create();
    $other = Product::factory()->create();

    $this->patchJson(route('v1.inventory.products.update', $other), ['sku' => $product->sku])
        ->assertUnprocessable()->assertJsonValidationErrors('sku');

    $this->assertDatabaseHas(Product::class, ['id' => $other->id, 'sku' => $other->sku]);
});

test('rejects unknown categories and brands', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create();

    $this->patchJson(route('v1.inventory.products.update', $product), ['category_id' => 999999, 'brand_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'brand_id']);

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'category_id' => $product->category_id, 'brand_id' => $product->brand_id]);
});

test('returns 404 for an unknown product', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->getJson(route('v1.inventory.products.show', 999999))->assertNotFound();
});

test('searches products by name or SKU', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $keyboard = Product::factory()->create(['is_active' => true, 'name' => 'Wireless Keyboard', 'sku' => 'KB-001']);
    $mouse = Product::factory()->create(['is_active' => true, 'name' => 'Mouse', 'sku' => 'MS-001']);

    $this->getJson(route('v1.inventory.products.search', ['q' => 'Keyboard']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $keyboard->id);
    $this->getJson(route('v1.inventory.products.search', ['q' => 'MS-001']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mouse->id);
});

test('returns at most ten product search results without pagination', function () {
    $this->actingAs(User::factory()->create(), 'api');

    Product::factory()->count(12)->create(['is_active' => true, 'name' => 'Keyboard']);

    $this->getJson(route('v1.inventory.products.search', ['q' => 'Keyboard']))
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('links');
});

test('excludes inactive products from name and SKU search results', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $active = Product::factory()->create(['is_active' => true, 'name' => 'Keyboard']);
    Product::factory()->create(['name' => 'Keyboard Case', 'is_active' => false]);
    Product::factory()->create(['name' => 'Adapter', 'sku' => 'Keyboard-001', 'is_active' => false]);

    $this->getJson(route('v1.inventory.products.search', ['q' => 'Keyboard']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->id);
});

test('requires a product search term', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->getJson(route('v1.inventory.products.search'))
        ->assertUnprocessable()->assertJsonValidationErrors('q');
});

test('returns an empty list when no products match', function () {
    $this->actingAs(User::factory()->create(), 'api');

    Product::factory()->create(['name' => 'Keyboard', 'sku' => 'KB-001']);

    $this->getJson(route('v1.inventory.products.search', ['q' => 'Mouse']))
        ->assertOk()->assertExactJson(['data' => []]);
});
