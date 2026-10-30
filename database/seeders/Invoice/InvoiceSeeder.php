<?php

namespace Database\Seeders\Invoice;

use App\Models\Inventory\Product;
use App\Services\Invoice\InvoiceService;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(InvoiceService $invoiceService): void
    {
        $product = Product::query()->where('is_active', true)->first();

        if (! $product) {
            $product = Product::factory()->create(['is_active' => true]);
        }

        $invoiceService->create([
            'invoice_date' => now()->toDateString(),
            'customer_name' => 'Sample Customer',
            'customer_phone' => '09123456789',
            'delivery_phone' => '09987654321',
            'delivery_address' => 'Sample delivery address, Yangon',
            'payment_type' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
    }
}
