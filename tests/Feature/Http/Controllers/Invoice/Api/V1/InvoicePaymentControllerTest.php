<?php

use App\Models\Inventory\Product;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\PaymentPlatform;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('creates a paid invoice with the selected platform', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 5]);
    $platform = PaymentPlatform::factory()->create(['name' => 'KBZPay']);

    $response = $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product, [
        'payment_type' => 'paid',
        'payment_platform_id' => $platform->id,
    ], quantity: 1));

    $response->assertCreated()
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_platform.name', 'KBZPay');
    $this->assertDatabaseHas(Invoice::class, ['id' => $response->json('data.id'), 'payment_status' => 'paid', 'payment_platform_id' => $platform->id]);
});

test('requires a payment platform for paid invoices', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 5]);

    $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product, ['payment_type' => 'paid'], quantity: 1))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_platform_id');

    $this->assertDatabaseEmpty(Invoice::class);
});

test('rejects an unknown payment platform', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 5]);

    $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product, [
        'payment_type' => 'paid',
        'payment_platform_id' => 999999,
    ], quantity: 1))->assertUnprocessable()
        ->assertJsonValidationErrors('payment_platform_id');

    $this->assertDatabaseEmpty(Invoice::class);
});

test('keeps COD unpaid until money is collected', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 5]);

    $response = $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product, ['payment_status' => 'paid'], quantity: 1));

    $response->assertCreated()
        ->assertJsonPath('data.payment_status', 'unpaid');
    $this->assertDatabaseHas(Invoice::class, ['id' => $response->json('data.id'), 'payment_status' => 'unpaid']);
});

test('records a collected COD payment without changing the invoice total', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $invoice = Invoice::factory()->create(['grand_total' => 15000, 'delivery_status' => 'delivered']);

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), ['payment_status' => 'paid'])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.grand_total', 15000);

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'payment_status' => 'paid', 'grand_total' => 15000]);
});

test('records the platform for an electronic COD payment', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $invoice = Invoice::factory()->create(['delivery_status' => 'delivered']);
    $platform = PaymentPlatform::factory()->create();

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), [
        'payment_status' => 'paid',
        'payment_platform_id' => $platform->id,
    ])->assertOk()
        ->assertJsonPath('data.payment_platform.id', $platform->id);

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'payment_status' => 'paid', 'payment_platform_id' => $platform->id]);
});

test('does not change an already recorded payment', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->freezeTime();
    $invoice = Invoice::factory()->paid()->create();
    $updatedAt = $invoice->updated_at->toISOString();
    $this->travel(1)->minutes();

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), ['payment_status' => 'paid'])
        ->assertOk()
        ->assertJsonPath('data.updated_at', $updatedAt);
});

test('rejects changing the platform of a recorded payment', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $invoice = Invoice::factory()->paid()->create();
    $other = PaymentPlatform::factory()->create();

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), [
        'payment_status' => 'paid',
        'payment_platform_id' => $other->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('payment_platform_id');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'payment_platform_id' => $invoice->payment_platform_id]);
});

test('rejects payments for cancelled invoices', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $invoice = Invoice::factory()->create(['status' => 'cancelled']);

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), ['payment_status' => 'paid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_status');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'payment_status' => 'unpaid']);
});

test('returns 422 for COD payments when delivery is not completed', function (string $deliveryStatus) {
    $this->actingAs(User::factory()->create(), 'api');

    $invoice = Invoice::factory()->create(['delivery_status' => $deliveryStatus]);

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), ['payment_status' => 'paid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_status')
        ->assertJsonPath('errors.payment_status.0', 'COD payments can only be recorded after delivery.');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'payment_status' => 'unpaid', 'payment_platform_id' => null]);
})->with(['pending', 'preparing', 'ready_for_dispatch', 'on_the_way', 'failed_delivery', 'cancelled', 'returned']);
