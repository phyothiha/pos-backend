<?php

namespace App\Services\Invoice;

use App\Models\Invoice\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceDeliveryService
{
    private const TRANSITIONS = [
        'pending' => ['preparing', 'cancelled'],
        'preparing' => ['ready_for_dispatch', 'cancelled'],
        'ready_for_dispatch' => ['on_the_way', 'cancelled'],
        'on_the_way' => ['delivered', 'failed_delivery'],
        'failed_delivery' => ['on_the_way', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public function __construct(private readonly InvoiceStatusService $invoiceStatusService) {}

    public function update(Invoice $invoice, string $status, int $userId): Invoice
    {
        return DB::transaction(function () use ($invoice, $status, $userId): Invoice {
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $lockedInvoice->load(['items', 'paymentPlatform']);

            if ($lockedInvoice->delivery_status === $status) {
                return $lockedInvoice->load('deliveryStatusHistories.user');
            }

            if (! in_array($status, self::TRANSITIONS[$lockedInvoice->delivery_status] ?? [], true)) {
                throw ValidationException::withMessages(['delivery_status' => 'This delivery status change is not allowed.']);
            }

            if ($status === 'preparing' || $status === 'cancelled') {
                return $this->invoiceStatusService->update($lockedInvoice, $status === 'preparing' ? 'confirmed' : 'cancelled', $userId);
            }

            if ($lockedInvoice->status !== 'confirmed') {
                throw ValidationException::withMessages(['delivery_status' => 'Confirm the invoice before updating delivery.']);
            }

            if ($status === 'returned') {
                $this->invoiceStatusService->restoreStock($lockedInvoice, $userId, 'return', 'Returned goods received');
            }

            $fromStatus = $lockedInvoice->delivery_status;
            $lockedInvoice->update(['delivery_status' => $status]);
            $lockedInvoice->deliveryStatusHistories()->create([
                'user_id' => $userId,
                'from_status' => $fromStatus,
                'to_status' => $status,
            ]);

            return $lockedInvoice->load('deliveryStatusHistories.user');
        }, 3);
    }
}
