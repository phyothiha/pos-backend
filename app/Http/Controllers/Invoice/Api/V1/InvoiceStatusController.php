<?php

namespace App\Http\Controllers\Invoice\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\Api\V1\UpdateInvoiceStatusRequest;
use App\Http\Resources\Invoice\Api\V1\InvoiceResource;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceStatusService;

class InvoiceStatusController extends Controller
{
    public function __construct(private readonly InvoiceStatusService $invoiceStatusService) {}

    public function update(UpdateInvoiceStatusRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceStatusService->update($invoice, $request->validated('status'), $request->user()->id));
    }
}
