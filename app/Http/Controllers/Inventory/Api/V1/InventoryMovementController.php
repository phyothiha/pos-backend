<?php

namespace App\Http\Controllers\Inventory\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\Api\V1\InventoryMovementResource;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryMovementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['product_id' => ['sometimes', 'required', 'integer', 'exists:products,id']]);

        $movements = InventoryMovement::query()->with(['product', 'invoiceItem', 'user'])
            ->when(isset($data['product_id']), fn ($query) => $query->where('product_id', $data['product_id']))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return InventoryMovementResource::collection($movements);
    }
}
