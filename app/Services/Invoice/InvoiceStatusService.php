<?php

namespace App\Services\Invoice;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Invoice\Invoice;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceStatusService
{
    public function __construct(private readonly InventoryMovementService $inventoryMovementService) {}

    public function update(Invoice $invoice, string $status, int $userId): Invoice
    {
        return DB::transaction(function () use ($invoice, $status, $userId): Invoice {
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $lockedInvoice->load(['items', 'paymentPlatform']);

            if ($lockedInvoice->status === $status) {
                return $lockedInvoice;
            }

            if ($lockedInvoice->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'Cancelled invoices cannot be confirmed.']);
            }

            if (! in_array($lockedInvoice->delivery_status, ['pending', 'preparing', 'ready_for_dispatch'], true)) {
                throw ValidationException::withMessages(['status' => 'Dispatched or completed orders require a return workflow.']);
            }

            if ($status === 'confirmed') {
                $this->confirm($lockedInvoice, $userId);
            } else {
                $this->cancel($lockedInvoice, $userId);
            }

            $lockedInvoice->update([
                'status' => $status,
                'delivery_status' => $status === 'confirmed' ? 'preparing' : 'cancelled',
            ]);

            return $lockedInvoice;
        }, 3);
    }

    private function confirm(Invoice $invoice, int $userId): void
    {
        if ($invoice->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'An invoice must contain at least one product.']);
        }

        $products = Product::query()->whereIn('id', $invoice->items->pluck('product_id'))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($invoice->items as $item) {
            $product = $products->get($item->product_id);

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages(['items' => "Product {$item->product_name} is no longer available."]);
            }

            if ($product->stock_quantity < $item->quantity) {
                throw ValidationException::withMessages(['stock_quantity' => "Insufficient stock for {$item->product_name}."]);
            }

            $this->inventoryMovementService->record($product, -$item->quantity, 'sale', 'Invoice confirmed', $userId, $item->id);
        }
    }

    private function cancel(Invoice $invoice, int $userId): void
    {
        if ($invoice->status === 'draft') {
            return;
        }

        $sales = InventoryMovement::query()->whereIn('invoice_item_id', $invoice->items->pluck('id'))
            ->where('type', 'sale')->orderBy('id')->get();
        $products = Product::query()->whereIn('id', $sales->pluck('product_id'))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($sales as $sale) {
            $this->inventoryMovementService->record(
                $products->get($sale->product_id), -$sale->quantity, 'cancellation',
                'Invoice cancelled', $userId, $sale->invoice_item_id,
            );
        }
    }
}
