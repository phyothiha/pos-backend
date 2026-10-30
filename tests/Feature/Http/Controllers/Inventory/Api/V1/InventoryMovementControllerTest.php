<?php

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('lists inventory history newest first with pagination and product filtering', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');
    $product = Product::factory()->create(['stock_quantity' => 16]);
    for ($balance = 1; $balance <= 16; $balance++) {
        $product->inventoryMovements()->create([
            'type' => 'receipt', 'quantity' => 1, 'balance_after' => $balance,
            'reason' => 'Stock received', 'user_id' => $user->id,
        ]);
    }
    $other = Product::factory()->create(['stock_quantity' => 2]);
    $other->inventoryMovements()->create([
        'type' => 'opening', 'quantity' => 2, 'balance_after' => 2, 'reason' => 'Initial product stock',
    ]);

    $this->getJson(route('v1.inventory.movements.index', ['product_id' => $product->id, 'page' => 2]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 16)
        ->assertJsonPath('data.0.product_id', $product->id)->assertJsonPath('data.0.user_name', $user->name);
    $this->getJson(route('v1.inventory.movements.index'))
        ->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 17);
});

test('records opening stock when creating a product', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');
    $attributes = Product::factory()->make(['stock_quantity' => 12])->getAttributes();

    $response = $this->postJson(route('v1.inventory.products.store'), $attributes)
        ->assertCreated()->assertJsonPath('data.stock_quantity', 12);

    $this->assertDatabaseHas(InventoryMovement::class, [
        'product_id' => $response->json('data.id'), 'type' => 'opening', 'quantity' => 12,
        'balance_after' => 12, 'user_id' => $user->id,
    ]);
});

test('records stock receipts and adjustments from product edits', function (int $quantity, string $type, int $difference) {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');
    $product = Product::factory()->create(['stock_quantity' => 10]);

    $this->patchJson(route('v1.inventory.products.update', $product), [
        'stock_quantity' => $quantity,
    ])->assertOk()->assertJsonPath('data.stock_quantity', $quantity);

    $this->assertDatabaseHas(InventoryMovement::class, [
        'product_id' => $product->id, 'type' => $type, 'quantity' => $difference,
        'balance_after' => $quantity, 'reason' => 'Product stock updated', 'user_id' => $user->id,
    ]);
})->with([[15, 'receipt', 5], [7, 'adjustment', -3]]);

test('does not record a movement when the stock is unchanged or absent', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $product = Product::factory()->create(['stock_quantity' => 10]);

    $this->patchJson(route('v1.inventory.products.update', $product), ['stock_quantity' => 10])->assertOk();
    $this->patchJson(route('v1.inventory.products.update', $product), ['name' => 'Updated product'])->assertOk();

    $this->assertDatabaseEmpty(InventoryMovement::class);
});

test('rejects invalid stock balances on product edits with 422', function (mixed $quantity) {
    $this->actingAs(User::factory()->create(), 'api');
    $product = Product::factory()->create(['stock_quantity' => 10]);

    $this->patchJson(route('v1.inventory.products.update', $product), ['stock_quantity' => $quantity])
        ->assertUnprocessable()->assertJsonValidationErrors('stock_quantity');

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 10]);
    $this->assertDatabaseEmpty(InventoryMovement::class);
})->with([-1, 2147483648, 1.5, null]);

test('keeps inventory history when a product deletion is attempted', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $product = Product::factory()->create(['stock_quantity' => 10]);
    $movement = $product->inventoryMovements()->create([
        'type' => 'opening', 'quantity' => 10, 'balance_after' => 10, 'reason' => 'Initial product stock',
    ]);

    $this->deleteJson(route('v1.inventory.products.destroy', $movement->product_id))
        ->assertUnprocessable()->assertJsonValidationErrors('product');

    $this->assertModelExists($movement);
    $this->assertModelExists($movement->product);
});

test('requires authentication for inventory history', function () {
    $this->getJson(route('v1.inventory.movements.index'))->assertUnauthorized();
});

test('rejects an unknown history product filter with 422', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->getJson(route('v1.inventory.movements.index', ['product_id' => 999999]))
        ->assertUnprocessable()->assertJsonValidationErrors('product_id');
});

test('seeds setup data without sample invoices or inventory movements', function () {
    $this->seed();

    $this->assertDatabaseCount(Product::class, 10);
    $this->assertDatabaseEmpty(Invoice::class);
    $this->assertDatabaseEmpty(InvoiceItem::class);
    $this->assertDatabaseEmpty(InventoryMovement::class);
});

test('ignores supplied stock reasons and records automatic history', function (mixed $reason) {
    $this->actingAs(User::factory()->create(), 'api');
    $product = Product::factory()->create(['stock_quantity' => 10]);

    $this->patchJson(route('v1.inventory.products.update', $product), ['stock_quantity' => 5, 'stock_reason' => $reason])
        ->assertOk()->assertJsonPath('data.stock_quantity', 5);

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 5]);
    $this->assertDatabaseHas(InventoryMovement::class, [
        'product_id' => $product->id, 'quantity' => -5, 'balance_after' => 5,
        'type' => 'adjustment', 'reason' => 'Product stock updated',
    ]);
})->with(['Custom stock reason', 12, str_repeat('x', 501)]);
