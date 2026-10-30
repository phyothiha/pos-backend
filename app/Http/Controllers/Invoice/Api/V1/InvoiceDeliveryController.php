<?php

namespace App\Http\Controllers\Invoice\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\Api\V1\UpdateInvoiceDeliveryRequest;
use App\Http\Resources\Invoice\Api\V1\InvoiceResource;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceDeliveryService;

class InvoiceDeliveryController extends Controller
{
    public function __construct(private readonly InvoiceDeliveryService $invoiceDeliveryService) {}

    public function update(UpdateInvoiceDeliveryRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceDeliveryService->update($invoice, $request->validated('delivery_status'), $request->user()->id));
    }
}
