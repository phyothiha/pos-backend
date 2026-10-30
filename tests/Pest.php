<?php

use App\Models\Inventory\Product;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function invoicePayload(Product $product, array $overrides = [], int $quantity = 3): array
{
    return array_replace([
        'invoice_date' => '2026-10-30',
        'customer_name' => 'Test Customer',
        'customer_phone' => '09123456789',
        'delivery_phone' => '09987654321',
        'delivery_address' => 'Yangon',
        'payment_type' => 'cod',
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
    ], $overrides);
}
