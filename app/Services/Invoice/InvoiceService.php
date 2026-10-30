<?php

namespace App\Services\Invoice;

use App\Models\Inventory\Product;
use App\Models\Invoice\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function paginate(): LengthAwarePaginator
    {
        return Invoice::query()->with(['items', 'paymentPlatform'])->latest()->orderByDesc('id')->paginate(15);
    }

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $products = Product::query()
                ->whereIn('id', array_column($data['items'], 'product_id'))
                ->lockForUpdate()
                ->get();
            $subtotal = 0;
            $items = [];

            foreach ($data['items'] as $item) {
                $product = $products->firstWhere('id', $item['product_id']);
                $unitPrice = $product->selling_price;
                $quantity = (int) $item['quantity'];

                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;

                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'product_type' => $product->type,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $discount = (int) ($data['discount'] ?? 0);
            $deliveryFee = (int) ($data['delivery_fee'] ?? 0);

            $grandTotal = $subtotal - $discount + $deliveryFee;

            $invoice = Invoice::query()->create([
                ...Arr::only($data, ['invoice_date', 'customer_name', 'customer_phone', 'delivery_phone', 'delivery_address', 'payment_type']),
                'invoice_number' => 'INV-'.Str::uuid(),
                'status' => 'draft',
                'payment_status' => $data['payment_type'] === 'paid' ? 'paid' : 'unpaid',
                'payment_platform_id' => $data['payment_type'] === 'paid' ? $data['payment_platform_id'] : null,
                'delivery_status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $deliveryFee,
                'grand_total' => $grandTotal,
            ]);
            $invoice->items()->createMany($items);

            return $invoice->load(['items', 'paymentPlatform']);
        }, 3);
    }
}
