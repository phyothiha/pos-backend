<?php

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Invoice\DeliveryStatusHistory;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('updates delivery and records the actor and time without collecting COD', function (string $from, string $to) {
    $this->freezeTime();
    $user = User::factory()->create();
    $this->actingAs($user, 'api');
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'delivery_status' => $from]);

    $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), ['delivery_status' => $to])
        ->assertOk()
        ->assertJsonPath('data.delivery_status', $to)
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.delivery_status_histories.0.user_name', $user->name);

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'delivery_status' => $to]);
    $this->assertDatabaseHas(DeliveryStatusHistory::class, [
        'invoice_id' => $invoice->id, 'from_status' => $from, 'to_status' => $to,
        'user_id' => $user->id, 'created_at' => now(),
    ]);
    $this->assertDatabaseEmpty(InventoryMovement::class);
    $this->getJson(route('v1.inventory.invoices.show', $invoice))->assertOk()
        ->assertJsonPath('data.delivery_status_histories.0.to_status', $to);
})->with([
    ['preparing', 'ready_for_dispatch'],
    ['ready_for_dispatch', 'on_the_way'],
    ['on_the_way', 'delivered'],
    ['on_the_way', 'failed_delivery'],
    ['failed_delivery', 'on_the_way'],
]);

test('rejects invalid delivery transitions with 422', function (string $from, string $to) {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'delivery_status' => $from]);

    $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), ['delivery_status' => $to])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('delivery_status');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'delivery_status' => $from]);
    $this->assertDatabaseEmpty(DeliveryStatusHistory::class);
    $this->assertDatabaseEmpty(InventoryMovement::class);
})->with([
    ['preparing', 'delivered'], ['delivered', 'cancelled'],
    ['on_the_way', 'cancelled'], ['cancelled', 'preparing'], ['returned', 'on_the_way'],
]);

test('restores stock only on return and only once when retried', function (string $from) {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');
    $invoice = Invoice::factory()->create();
    $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10]);
    $item = InvoiceItem::factory()->for($invoice)->for($product)->create(['quantity' => 3]);
    $this->patchJson(route('v1.inventory.invoices.status.update', $invoice), ['status' => 'confirmed'])->assertOk();
    foreach (['ready_for_dispatch', 'on_the_way', $from] as $status) {
        $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), ['delivery_status' => $status])->assertOk();
    }
    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 7]);

    $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), ['delivery_status' => 'returned'])
        ->assertOk()
        ->assertJsonPath('data.delivery_status', 'returned');
    $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), ['delivery_status' => 'returned'])->assertOk();

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 10]);
    $this->assertDatabaseHas(InventoryMovement::class, [
        'invoice_item_id' => $item->id, 'type' => 'return', 'quantity' => 3, 'user_id' => $user->id,
    ]);
    $this->assertDatabaseCount(InventoryMovement::class, 2);
    $this->assertDatabaseCount(DeliveryStatusHistory::class, 5);
})->with(['delivered', 'failed_delivery']);

test('uses invoice confirmation and cancellation for delivery changes and logs both once', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();
    $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 10]);
    InvoiceItem::factory()->for($invoice)->for($product)->create(['quantity' => 3]);

    $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), ['delivery_status' => 'preparing'])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');
    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 7]);
    $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), ['delivery_status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
    $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), ['delivery_status' => 'cancelled'])->assertOk();

    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 10]);
    $this->assertDatabaseCount(DeliveryStatusHistory::class, 2);
});

test('rejects dispatch of a draft invoice with 422', function () {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create(['delivery_status' => 'ready_for_dispatch']);

    $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), ['delivery_status' => 'on_the_way'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('delivery_status');
    $this->assertDatabaseEmpty(DeliveryStatusHistory::class);
});

test('validates delivery status with 422', function (array $payload) {
    $this->actingAs(User::factory()->create(), 'api');
    $invoice = Invoice::factory()->create();

    $this->patchJson(route('v1.inventory.invoices.delivery.update', $invoice), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('delivery_status');
    $this->assertDatabaseEmpty(DeliveryStatusHistory::class);
})->with([
    'missing status' => [[]],
    'unknown status' => [['delivery_status' => 'invalid']],
]);

test('requires authentication to update delivery with 401', function () {
    $this->patchJson('/api/v1/inventory/invoices/1/delivery', ['delivery_status' => 'delivered'])->assertUnauthorized();
});
