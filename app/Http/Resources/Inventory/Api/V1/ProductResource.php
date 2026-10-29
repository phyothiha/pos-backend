<?php

namespace App\Http\Resources\Inventory\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'selling_price' => $this->selling_price,
            'stock_quantity' => $this->stock_quantity,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
