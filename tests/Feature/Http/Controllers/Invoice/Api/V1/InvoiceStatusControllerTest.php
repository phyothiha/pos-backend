<?php

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('confirms an invoice and deducts stock once when retried', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');
    $invoice = Invoice::factory()->create();
    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 10]);
    $item = InvoiceItem::factory()->for($invoice)->for($product)->create(['quantity' => 3]);

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])
        ->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.delivery_status', 'preparing')
        ->assertJsonPath('data.payment_status', 'unpaid');
    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])->assertOk();

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 7]);
    $this->assertDatabaseCount(InventoryMovement::class, 1);
    $this->assertDatabaseHas(InventoryMovement::class, [
        'invoice_item_id' => $item->id, 'product_id' => $product->id,
        'type' => 'sale', 'quantity' => -3, 'balance_after' => 7, 'user_id' => $user->id,
    ]);
});

test('rolls back all stock changes when any product has insufficient stock', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();
    $available = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 10]);
    $unavailable = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 1]);
    InvoiceItem::factory()->for($invoice)->for($available)->create(['quantity' => 3]);
    InvoiceItem::factory()->for($invoice)->for($unavailable)->create(['quantity' => 2]);

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])
        ->assertUnprocessable()->assertJsonValidationErrors('stock_quantity');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'status' => 'draft', 'delivery_status' => 'pending']);
    $this->assertDatabaseHas(Product::class, ['id' => $available->id, 'stock_quantity' => 10]);
    $this->assertDatabaseHas(Product::class, ['id' => $unavailable->id, 'stock_quantity' => 1]);
    $this->assertDatabaseEmpty(InventoryMovement::class);
});

test('deducts and restores pre order quantity even after the product type changes', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();
    $product = Product::factory()->create(['is_active' => true, 'type' => 'pre_order', 'stock_quantity' => 10]);
    InvoiceItem::factory()->for($invoice)->for($product)->create(['quantity' => 5]);
    $product->update(['type' => 'in_stock']);

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])->assertOk();
    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 5]);
    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'cancelled'])->assertOk();

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 10]);
    $this->assertDatabaseCount(InventoryMovement::class, 2);
});

test('restores actual deductions once on cancellation even after product type changes', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');
    $invoice = Invoice::factory()->paid()->create();
    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 10]);
    $item = InvoiceItem::factory()->for($invoice)->for($product)->create(['quantity' => 3]);
    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])->assertOk();
    $product->update(['type' => 'pre_order', 'is_active' => false]);

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'cancelled'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.delivery_status', 'cancelled')
        ->assertJsonPath('data.payment_status', 'paid');
    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'cancelled'])->assertOk();

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 10]);
    $this->assertDatabaseCount(InventoryMovement::class, 2);
    $this->assertDatabaseHas(InventoryMovement::class, [
        'invoice_item_id' => $item->id, 'type' => 'cancellation', 'quantity' => 3,
        'balance_after' => 10, 'user_id' => $user->id,
    ]);
});

test('cancels a draft without adding stock and rejects reconfirming it', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 10]);
    InvoiceItem::factory()->for($invoice)->for($product)->create();

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'cancelled'])->assertOk();
    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 10]);
    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'status' => 'cancelled']);
    $this->assertDatabaseEmpty(InventoryMovement::class);
});

test('rejects cancellation after dispatch or completion with 422', function (string $deliveryStatus) {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'delivery_status' => $deliveryStatus]);

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'cancelled'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'status' => 'confirmed', 'delivery_status' => $deliveryStatus]);
    $this->assertDatabaseEmpty(InventoryMovement::class);
})->with(['on_the_way', 'delivered', 'failed_delivery', 'returned']);

test('rejects confirmation of an empty invoice with 422', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])
        ->assertUnprocessable()->assertJsonValidationErrors('items');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'status' => 'draft']);
    $this->assertDatabaseEmpty(InventoryMovement::class);
});

test('rejects confirmation when a product becomes inactive with 422', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();
    $product = Product::factory()->create(['is_active' => false, 'stock_quantity' => 10]);
    InvoiceItem::factory()->for($invoice)->for($product)->create();

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])
        ->assertUnprocessable()->assertJsonValidationErrors('items');

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 10]);
    $this->assertDatabaseEmpty(InventoryMovement::class);
});

test('does not oversell when two invoices demand the same stock', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 5]);
    $first = Invoice::factory()->create();
    $second = Invoice::factory()->create();
    InvoiceItem::factory()->for($first)->for($product)->create(['quantity' => 4]);
    InvoiceItem::factory()->for($second)->for($product)->create(['quantity' => 4]);

    $this->patchJson(route('v1.inventory.invoices.status.update', $first), ['status' => 'confirmed'])->assertOk();
    $this->patchJson(route('v1.inventory.invoices.status.update', $second), ['status' => 'confirmed'])
        ->assertUnprocessable()->assertJsonValidationErrors('stock_quantity');

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 1]);
    $this->assertDatabaseHas(Invoice::class, ['id' => $second->id, 'status' => 'draft']);
    $this->assertDatabaseCount(InventoryMovement::class, 1);
});

test('validates invoice status with 422', function (array $payload) {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('status');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'status' => 'draft']);
})->with([[[]], [['status' => 'draft']], [['status' => 'invalid']]]);

test('requires authentication to update invoice status', function () {
    $this->patchJson('/api/v1/inventory/invoices/1/status', ['status' => 'confirmed'])->assertUnauthorized();
});

test('returns 404 when updating the status of an unknown invoice', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->patchJson('/api/v1/inventory/invoices/999999/status', ['status' => 'confirmed'])->assertNotFound();
});

test('confirms a mixed invoice and deducts both product types', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();
    $stocked = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 5]);
    $preorder = Product::factory()->create(['is_active' => true, 'type' => 'pre_order', 'stock_quantity' => 20]);
    InvoiceItem::factory()->for($invoice)->for($stocked)->create(['quantity' => 5]);
    InvoiceItem::factory()->for($invoice)->for($preorder)->create(['quantity' => 10]);

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])->assertOk();

    $this->assertDatabaseHas(Product::class, ['id' => $stocked->id, 'stock_quantity' => 0]);
    $this->assertDatabaseHas(Product::class, ['id' => $preorder->id, 'stock_quantity' => 10]);
    $this->assertDatabaseCount(InventoryMovement::class, 2);
    $this->getJson(route('v1.inventory.movements.index', ['product_id' => $stocked->id]))
        ->assertOk()->assertJsonPath('data.0.invoice_id', $invoice->id)->assertJsonPath('data.0.quantity', -5);
});

test('rolls back cancellation when restoring stock would overflow', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();
    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 10]);
    InvoiceItem::factory()->for($invoice)->for($product)->create(['quantity' => 3]);
    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])->assertOk();
    $this->patchJson(route('v1.inventory.products.update', $product), ['stock_quantity' => 2147483647])->assertOk();

    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'cancelled'])
        ->assertUnprocessable()->assertJsonValidationErrors('stock_quantity');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 2147483647]);
    $this->assertDatabaseCount(InventoryMovement::class, 2);
});
