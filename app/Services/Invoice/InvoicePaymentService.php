<?php

namespace App\Services\Invoice;

use App\Models\Invoice\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePaymentService
{
    /** @param array{payment_status: string, payment_platform_id?: int|null} $data */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status === 'cancelled') {
                throw ValidationException::withMessages(['payment_status' => 'Payments cannot be recorded for cancelled invoices.']);
            }

            $platformId = array_key_exists('payment_platform_id', $data)
                ? $data['payment_platform_id']
                : $lockedInvoice->payment_platform_id;

            if ($lockedInvoice->payment_status === 'paid') {
                if ($platformId != $lockedInvoice->payment_platform_id) {
                    throw ValidationException::withMessages(['payment_platform_id' => 'The platform of a recorded payment cannot be changed.']);
                }

                return $lockedInvoice->load(['items', 'paymentPlatform']);
            }

            if ($lockedInvoice->payment_type === 'cod' && $lockedInvoice->delivery_status !== 'delivered') {
                throw ValidationException::withMessages(['payment_status' => 'COD payments can only be recorded after delivery.']);
            }

            $lockedInvoice->update([
                'payment_status' => 'paid',
                'payment_platform_id' => $platformId,
            ]);

            return $lockedInvoice->load(['items', 'paymentPlatform']);
        }, 3);
    }
}
