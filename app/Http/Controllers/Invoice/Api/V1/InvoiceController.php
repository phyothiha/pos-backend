<?php

namespace App\Http\Controllers\Invoice\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\Api\V1\StoreInvoiceRequest;
use App\Http\Resources\Invoice\Api\V1\InvoiceResource;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index(): AnonymousResourceCollection
    {
        return InvoiceResource::collection($this->invoiceService->paginate());
    }

    public function store(StoreInvoiceRequest $request): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->create($request->validated()));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(['items', 'paymentPlatform']));
    }
}
