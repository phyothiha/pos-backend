<?php

namespace App\Http\Resources\Inventory\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product->name),
            'product_sku' => $this->whenLoaded('product', fn () => $this->product->sku),
            'invoice_item_id' => $this->invoice_item_id,
            'invoice_id' => $this->whenLoaded('invoiceItem', fn () => $this->invoiceItem?->invoice_id),
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'type' => $this->type,
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
