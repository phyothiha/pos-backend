<?php

namespace App\Http\Controllers\Invoice\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\Api\V1\StorePaymentPlatformRequest;
use App\Http\Resources\Invoice\Api\V1\PaymentPlatformResource;
use App\Services\Invoice\PaymentPlatformService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentPlatformController extends Controller
{
    public function __construct(private readonly PaymentPlatformService $paymentPlatformService) {}

    public function all(): AnonymousResourceCollection
    {
        return PaymentPlatformResource::collection($this->paymentPlatformService->all());
    }

    public function index(): AnonymousResourceCollection
    {
        return PaymentPlatformResource::collection($this->paymentPlatformService->paginate());
    }

    public function store(StorePaymentPlatformRequest $request): PaymentPlatformResource
    {
        return new PaymentPlatformResource($this->paymentPlatformService->create($request->validated()));
    }
}
