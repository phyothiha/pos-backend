<?php

use App\Models\Inventory\Product;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(LazilyRefreshDatabase::class);

function invoicePayload(Product $product, array $overrides = []): array
{
    return array_replace([
        'invoice_date' => '2026-10-30',
        'customer_name' => 'Test Customer',
        'customer_phone' => '09123456789',
        'delivery_phone' => '09987654321',
        'delivery_address' => 'Yangon',
        'payment_type' => 'cod',
        'items' => [['product_id' => $product->id, 'quantity' => 3]],
    ], $overrides);
}

test('creates an invoice and calculates totals in whole kyat', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $keyboard = Product::factory()->create(['is_active' => true, 'selling_price' => 15000, 'stock_quantity' => 10]);
    $mouse = Product::factory()->create(['is_active' => true, 'selling_price' => 5000, 'type' => 'pre_order']);

    $response = $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($keyboard, [
        'discount' => 1000,
        'delivery_fee' => 2000,
        'grand_total' => 1,
        'items' => [
            ['product_id' => $keyboard->id, 'quantity' => 2],
            ['product_id' => $mouse->id, 'quantity' => 1],
        ],
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.subtotal', 35000)
        ->assertJsonPath('data.grand_total', 36000)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.line_total', 30000)
        ->assertJsonPath('data.items.1.product_type', 'pre_order');
    $this->assertDatabaseHas(Invoice::class, ['id' => $response->json('data.id'), 'grand_total' => 36000]);
    $this->assertDatabaseHas(InvoiceItem::class, ['invoice_id' => $response->json('data.id'), 'product_id' => $keyboard->id, 'unit_price' => 15000, 'line_total' => 30000]);
    $this->assertDatabaseHas(Product::class, ['id' => $keyboard->id, 'stock_quantity' => 10]);
});

test('generates unique invoice numbers and defaults optional fees to zero', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true, 'selling_price' => 15000]);

    $first = $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product));
    $second = $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product));

    $first->assertCreated()->assertJsonPath('data.discount', 0)->assertJsonPath('data.delivery_fee', 0);
    $second->assertCreated();
    expect($first->json('data.invoice_number'))->toStartWith('INV-')->not->toBe($second->json('data.invoice_number'));
    $this->assertDatabaseCount(Invoice::class, 2);
});

test('keeps invoice product details and prices after the product changes', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true, 'name' => 'Keyboard', 'sku' => 'KB-001', 'selling_price' => 15000]);
    $created = $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product))->assertCreated();
    $product->update(['name' => 'New Keyboard', 'sku' => 'KB-002', 'selling_price' => 20000]);

    $this->getJson(route('v1.inventory.invoices.show', $created->json('data.id')))
        ->assertOk()
        ->assertJsonPath('data.items.0.product_name', 'Keyboard')
        ->assertJsonPath('data.items.0.product_sku', 'KB-001')
        ->assertJsonPath('data.items.0.unit_price', 15000)
        ->assertJsonPath('data.grand_total', 45000);
});

test('requires customer details and invoice items', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->postJson(route('v1.inventory.invoices.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invoice_date', 'customer_name', 'customer_phone', 'delivery_phone', 'delivery_address', 'payment_type', 'items']);

    $this->assertDatabaseEmpty(Invoice::class);
});

test('rejects invalid quantities and decimal fees', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true]);

    $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product, [
        'discount' => 0.5,
        'delivery_fee' => -1,
        'items' => [['product_id' => $product->id, 'quantity' => 0]],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['discount', 'delivery_fee', 'items.0.quantity']);

    $this->assertDatabaseEmpty(Invoice::class);
    $this->assertDatabaseEmpty(InvoiceItem::class);
});

test('rejects inactive products', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => false]);

    $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product))
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');

    $this->assertDatabaseEmpty(Invoice::class);
});

test('rejects duplicate products in one invoice', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true]);

    $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product, [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');

    $this->assertDatabaseEmpty(Invoice::class);
});

test('does not accept prices supplied by the client', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true]);

    $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product, [
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('items.0');

    $this->assertDatabaseEmpty(Invoice::class);
});

test('lists invoices newest first with pagination', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->freezeTime();
    $invoices = Invoice::factory()->count(16)->create();

    $this->getJson(route('v1.inventory.invoices.index'))
        ->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('data.0.id', $invoices->last()->id);
    $this->getJson(route('v1.inventory.invoices.index', ['page' => 2]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 16);
});

test('returns 404 for an unknown invoice', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->getJson(route('v1.inventory.invoices.show', 999999))->assertNotFound();
});

test('prevents deleting products used in invoices', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $item = InvoiceItem::factory()->create();

    $this->deleteJson(route('v1.inventory.products.destroy', $item->product_id))
        ->assertUnprocessable()->assertJsonValidationErrors('product');

    $this->assertModelExists($item->product);
    $this->assertModelExists($item);
});

test('rolls back the invoice if an item cannot be saved', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $product = Product::factory()->create(['is_active' => true]);
    Event::listen('eloquent.creating: '.InvoiceItem::class, function (): void {
        throw new RuntimeException('Item save failed.');
    });

    try {
        $this->postJson(route('v1.inventory.invoices.store'), invoicePayload($product))->assertInternalServerError();

        $this->assertDatabaseEmpty(Invoice::class);
        $this->assertDatabaseEmpty(InvoiceItem::class);
    } finally {
        Event::forget('eloquent.creating: '.InvoiceItem::class);
    }
});
