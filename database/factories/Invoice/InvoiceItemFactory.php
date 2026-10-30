<?php

namespace Database\Factories\Invoice;

use App\Models\Inventory\Product;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceItem> */
class InvoiceItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => Product::factory(),
            'product_name' => fn (array $attributes) => Product::findOrFail($attributes['product_id'])->name,
            'product_sku' => fn (array $attributes) => Product::findOrFail($attributes['product_id'])->sku,
            'product_type' => fn (array $attributes) => Product::findOrFail($attributes['product_id'])->type,
            'quantity' => 1,
            'unit_price' => fn (array $attributes) => Product::findOrFail($attributes['product_id'])->selling_price,
            'line_total' => fn (array $attributes) => $attributes['unit_price'] * $attributes['quantity'],
        ];
    }
}
