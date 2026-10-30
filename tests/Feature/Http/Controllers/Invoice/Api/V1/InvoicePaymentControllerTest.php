<?php

use App\Models\Inventory\Product;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\PaymentPlatform;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function paymentInvoicePayload(Product $product): array
{
    return [
        'invoice_date' => '2026-10-30',
        'customer_name' => 'Test Customer',
        'customer_phone' => '09123456789',
        'delivery_phone' => '09987654321',
        'delivery_address' => 'Yangon',
        'payment_type' => 'paid',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ];
}

test('creates a paid invoice with the selected platform', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true]);
    $platform = PaymentPlatform::factory()->create(['name' => 'KBZPay']);

    $response = $this->postJson(route('v1.inventory.invoices.store'), [
        ...paymentInvoicePayload($product),
        'payment_platform_id' => $platform->id,
    ]);

    $response->assertCreated()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.payment_platform.name', 'KBZPay');
    $this->assertDatabaseHas(Invoice::class, ['id' => $response->json('data.id'), 'payment_status' => 'paid', 'payment_platform_id' => $platform->id]);
});

test('requires a payment platform for paid invoices', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true]);

    $this->postJson(route('v1.inventory.invoices.store'), paymentInvoicePayload($product))
        ->assertUnprocessable()->assertJsonValidationErrors('payment_platform_id');

    $this->assertDatabaseEmpty(Invoice::class);
});

test('rejects an unknown payment platform', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true]);

    $this->postJson(route('v1.inventory.invoices.store'), [
        ...paymentInvoicePayload($product),
        'payment_platform_id' => 999999,
    ])->assertUnprocessable()->assertJsonValidationErrors('payment_platform_id');

    $this->assertDatabaseEmpty(Invoice::class);
});

test('keeps COD unpaid until money is collected', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true]);

    $response = $this->postJson(route('v1.inventory.invoices.store'), [
        ...paymentInvoicePayload($product),
        'payment_type' => 'cod',
        'payment_status' => 'paid',
    ]);

    $response->assertCreated()->assertJsonPath('data.payment_status', 'unpaid');
    $this->assertDatabaseHas(Invoice::class, ['id' => $response->json('data.id'), 'payment_status' => 'unpaid']);
});

test('records a collected COD payment without changing the invoice total', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $invoice = Invoice::factory()->create(['grand_total' => 15000]);

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), ['payment_status' => 'paid'])
        ->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.grand_total', 15000);

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'payment_status' => 'paid', 'grand_total' => 15000]);
});

test('records the platform for an electronic COD payment', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $invoice = Invoice::factory()->create();
    $platform = PaymentPlatform::factory()->create();

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), [
        'payment_status' => 'paid',
        'payment_platform_id' => $platform->id,
    ])->assertOk()->assertJsonPath('data.payment_platform.id', $platform->id);

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'payment_status' => 'paid', 'payment_platform_id' => $platform->id]);
});

test('does not change an already recorded payment', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->freezeTime();
    $invoice = Invoice::factory()->paid()->create();
    $updatedAt = $invoice->updated_at->toISOString();
    $this->travel(1)->minutes();

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), ['payment_status' => 'paid'])
        ->assertOk()->assertJsonPath('data.updated_at', $updatedAt);
});

test('rejects changing the platform of a recorded payment', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $invoice = Invoice::factory()->paid()->create();
    $other = PaymentPlatform::factory()->create();

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), [
        'payment_status' => 'paid',
        'payment_platform_id' => $other->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('payment_platform_id');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'payment_platform_id' => $invoice->payment_platform_id]);
});

test('rejects payments for cancelled invoices', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $invoice = Invoice::factory()->create(['status' => 'cancelled']);

    $this->patchJson(route('v1.inventory.invoices.payment.update', $invoice), ['payment_status' => 'paid'])
        ->assertUnprocessable()->assertJsonValidationErrors('payment_status');

    $this->assertDatabaseHas(Invoice::class, ['id' => $invoice->id, 'payment_status' => 'unpaid']);
});
