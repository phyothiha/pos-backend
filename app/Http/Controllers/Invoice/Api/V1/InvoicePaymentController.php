<?php

namespace App\Http\Controllers\Invoice\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\Api\V1\UpdateInvoicePaymentRequest;
use App\Http\Resources\Invoice\Api\V1\InvoiceResource;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoicePaymentService;

class InvoicePaymentController extends Controller
{
    public function __construct(private readonly InvoicePaymentService $invoicePaymentService) {}

    public function update(UpdateInvoicePaymentRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoicePaymentService->update($invoice, $request->validated()));
    }
}
