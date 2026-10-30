<?php

namespace App\Http\Resources\Invoice\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date->toDateString(),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_phone' => $this->delivery_phone,
            'delivery_address' => $this->delivery_address,
            'status' => $this->status,
            'payment_type' => $this->payment_type,
            'payment_status' => $this->payment_status,
            'payment_platform_id' => $this->payment_platform_id,
            'payment_platform' => new PaymentPlatformResource($this->whenLoaded('paymentPlatform')),
            'delivery_status' => $this->delivery_status,
            'delivery_status_histories' => DeliveryStatusHistoryResource::collection($this->whenLoaded('deliveryStatusHistories')),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'delivery_fee' => $this->delivery_fee,
            'grand_total' => $this->grand_total,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
