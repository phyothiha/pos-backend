<?php

use App\Models\Inventory\Brand;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

dataset('invalid product fields', [
    'missing category' => ['category_id', null, 'The category id field is required.'],
    'invalid category type' => ['category_id', 'invalid', 'The category id field must be an integer.'],
    'unknown category' => ['category_id', 999999, 'The selected category id is invalid.'],
    'missing brand' => ['brand_id', null, 'The brand id field is required.'],
    'invalid brand type' => ['brand_id', 'invalid', 'The brand id field must be an integer.'],
    'unknown brand' => ['brand_id', 999999, 'The selected brand id is invalid.'],
    'missing name' => ['name', null, 'The name field is required.'],
    'invalid name type' => ['name', 123, 'The name field must be a string.'],
    'long name' => ['name', str_repeat('a', 256), 'The name field must not be greater than 255 characters.'],
    'missing SKU' => ['sku', null, 'The sku field is required.'],
    'invalid SKU type' => ['sku', 123, 'The sku field must be a string.'],
    'long SKU' => ['sku', str_repeat('a', 101), 'The sku field must not be greater than 100 characters.'],
    'missing price' => ['selling_price', null, 'The selling price field is required.'],
    'invalid price type' => ['selling_price', 'invalid', 'The selling price field must be a number.'],
    'price underflow' => ['selling_price', '-10000000000000.00', 'The selling price field must be at least -9999999999999.99.'],
    'price precision' => ['selling_price', '1.234', 'The selling price field must have 0-2 decimal places.'],
    'price overflow' => ['selling_price', '10000000000000.00', 'The selling price field must not be greater than 9999999999999.99.'],
    'stock underflow' => ['stock_quantity', -2147483649, 'The stock quantity field must be at least -2147483648.'],
    'fractional stock' => ['stock_quantity', 1.5, 'The stock quantity field must be an integer.'],
    'invalid stock type' => ['stock_quantity', 'invalid', 'The stock quantity field must be an integer.'],
    'stock overflow' => ['stock_quantity', 2147483648, 'The stock quantity field must not be greater than 2147483647.'],
    'missing type' => ['type', null, 'The type field is required.'],
    'invalid type' => ['type', 'unknown', 'The selected type is invalid.'],
    'missing active status' => ['is_active', null, 'The is active field is required.'],
    'invalid active status' => ['is_active', 'yes', 'The is active field must be true or false.'],
]);

describe('listing products', function () {
    test('returns products newest first with category and brand details', function () {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $products = Product::factory()->for($category)->for($brand)->count(16)
            ->sequence(fn (Sequence $sequence) => ['created_at' => now()->subDays($sequence->index === 0 ? 16 : $sequence->index - 1)])
            ->create();

        $this->getJson(route('v1.inventory.products.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $products->get(1)->id);

        $response = $this->getJson(route('v1.inventory.products.index', ['page' => 2]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $products->first()->id)
            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonPath('data.0.brand.id', $brand->id)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.per_page', 15);
    });

    test('returns an empty collection when no products exist', function () {
        $this->getJson(route('v1.inventory.products.index'))
            ->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('creating products', function () {
    test('creates a product with database defaults and ignores client controlled fields', function () {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $response = $this->postJson(route('v1.inventory.products.store'), [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Sample product',
            'sku' => 'SKU-001',
            'selling_price' => '1250.50',
            'is_active' => false,
            'id' => 999999,
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.selling_price', '1250.50')
            ->assertJsonPath('data.stock_quantity', 0)
            ->assertJsonPath('data.type', 'in_stock')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.brand.id', $brand->id);
        $this->assertDatabaseHas(Product::class, [
            'sku' => 'SKU-001',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'selling_price' => '1250.50',
            'stock_quantity' => 0,
            'type' => 'in_stock',
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing(Product::class, ['id' => 999999]);
        $this->assertDatabaseMissing(Product::class, ['created_at' => '2000-01-01 00:00:00']);
    });

    test('allows duplicate product names and a zero price', function () {
        $existing = Product::factory()->create();

        $response = $this->postJson(route('v1.inventory.products.store'), [
            'category_id' => $existing->category_id,
            'brand_id' => $existing->brand_id,
            'name' => $existing->name,
            'sku' => 'DIFFERENT-SKU',
            'selling_price' => 0,
            'stock_quantity' => 0,
            'type' => 'pre_order',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.selling_price', '0.00')
            ->assertJsonPath('data.type', 'pre_order');
        $this->assertDatabaseHas(Product::class, ['sku' => 'DIFFERENT-SKU', 'name' => $existing->name]);
        $this->assertDatabaseCount(Product::class, 2);
    });

    test('returns 422 when required fields are omitted', function () {
        $this->postJson(route('v1.inventory.products.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'brand_id', 'name', 'sku', 'selling_price', 'is_active']);

        $this->assertDatabaseEmpty(Product::class);
    });

    test('returns 422 for invalid product fields', function (string $field, mixed $value, string $message) {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $data = [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Sample product',
            'sku' => 'SKU-001',
            'selling_price' => '10.00',
            'is_active' => true,
        ];

        $response = $this->postJson(route('v1.inventory.products.store'), array_replace($data, [$field => $value]));

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseEmpty(Product::class);
    })->with('invalid product fields');

    test('returns 422 for a duplicate SKU', function () {
        $product = Product::factory()->create();

        $response = $this->postJson(route('v1.inventory.products.store'), [
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'name' => 'Another product',
            'sku' => $product->sku,
            'selling_price' => '10.00',
            'is_active' => true,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['sku' => 'The sku has already been taken.']);
        $this->assertDatabaseCount(Product::class, 1);
    });
});

describe('showing products', function () {
    test('returns the product resource with correctly cast values', function () {
        $product = Product::factory()->create(['selling_price' => '12.30', 'stock_quantity' => 3, 'is_active' => false]);

        $this->getJson(route('v1.inventory.products.show', $product))
            ->assertOk()->assertExactJson(['data' => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'name' => $product->name,
                'sku' => $product->sku,
                'selling_price' => '12.30',
                'stock_quantity' => 3,
                'type' => 'in_stock',
                'is_active' => false,
                'category' => [
                    'id' => $product->category_id,
                    'name' => $product->category->name,
                    'description' => $product->category->description,
                    'created_at' => $product->category->created_at->toISOString(),
                    'updated_at' => $product->category->updated_at->toISOString(),
                ],
                'brand' => [
                    'id' => $product->brand_id,
                    'name' => $product->brand->name,
                    'description' => $product->brand->description,
                    'created_at' => $product->brand->created_at->toISOString(),
                    'updated_at' => $product->brand->updated_at->toISOString(),
                ],
                'created_at' => $product->created_at->toISOString(),
                'updated_at' => $product->updated_at->toISOString(),
            ]]);
    });
});

describe('updating products', function () {
    test('stores signed values without applying stock workflow rules', function () {
        $product = Product::factory()->create();

        $response = $this->patchJson(route('v1.inventory.products.update', $product), [
            'selling_price' => '-12.30',
            'stock_quantity' => -5,
            'type' => 'in_stock',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.selling_price', '-12.30')
            ->assertJsonPath('data.stock_quantity', -5)
            ->assertJsonPath('data.type', 'in_stock');
        $this->assertDatabaseHas(Product::class, [
            'id' => $product->id,
            'selling_price' => '-12.30',
            'stock_quantity' => -5,
            'type' => 'in_stock',
        ]);
    });

    test('updates fields and relationships through PUT or PATCH', function (string $method) {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $data = [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Updated product',
            'sku' => 'UPDATED-SKU',
            'selling_price' => '99.95',
            'stock_quantity' => 12,
            'type' => 'pre_order',
            'is_active' => false,
        ];

        $response = $this->{$method}(route('v1.inventory.products.update', $product), $data);

        $response->assertOk()
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.brand.id', $brand->id)
            ->assertJsonPath('data.selling_price', '99.95')
            ->assertJsonPath('data.stock_quantity', 12)
            ->assertJsonPath('data.type', 'pre_order')
            ->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas(Product::class, ['id' => $product->id, ...$data]);
    })->with(['putJson', 'patchJson']);

    test('allows the same SKU and preserves fields omitted from a partial update', function () {
        $product = Product::factory()->create();

        $response = $this->patchJson(route('v1.inventory.products.update', $product), ['sku' => $product->sku, 'stock_quantity' => 0]);

        $response->assertOk()
            ->assertJsonPath('data.stock_quantity', 0)
            ->assertJsonPath('data.selling_price', $product->selling_price);
        $this->assertDatabaseHas(Product::class, [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'selling_price' => $product->selling_price,
            'type' => $product->type,
            'is_active' => $product->is_active,
            'stock_quantity' => 0,
        ]);
    });

    test('returns 422 for invalid updates without changing the product', function (string $field, mixed $value, string $message) {
        $product = Product::factory()->create();
        $original = $product->getRawOriginal();

        $response = $this->patchJson(route('v1.inventory.products.update', $product), [$field => $value]);

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseHas(Product::class, $original);
    })->with('invalid product fields');

    test('returns 422 when changing to another product SKU', function () {
        $product = Product::factory()->create();
        $other = Product::factory()->create();

        $response = $this->patchJson(route('v1.inventory.products.update', $product), ['sku' => $other->sku]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['sku' => 'The sku has already been taken.']);
        $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'sku' => $product->sku]);
    });
});

describe('deleting products', function () {
    test('deletes the product without deleting its category or brand', function () {
        $product = Product::factory()->create();
        $category = $product->category;
        $brand = $product->brand;

        $this->deleteJson(route('v1.inventory.products.destroy', $product))->assertNoContent();

        $this->assertModelMissing($product);
        $this->assertModelExists($category);
        $this->assertModelExists($brand);
    });
});

test('returns 404 for a missing product', function (string $method, string $action) {
    $this->{$method}(route('v1.inventory.products.'.$action, 999999))->assertNotFound();
})->with([
    'show' => ['getJson', 'show'],
    'update' => ['patchJson', 'update'],
    'delete' => ['deleteJson', 'destroy'],
]);
