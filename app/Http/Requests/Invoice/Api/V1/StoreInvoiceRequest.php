<?php

namespace App\Http\Requests\Invoice\Api\V1;

use App\Models\Inventory\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'delivery_phone' => ['required', 'string', 'max:30'],
            'delivery_address' => ['required', 'string'],
            'payment_type' => ['required', Rule::in(['paid', 'cod'])],
            'payment_platform_id' => ['required_if:payment_type,paid', 'nullable', 'integer', 'exists:payment_platforms,id'],
            'discount' => ['sometimes', 'required', 'integer', 'min:0'],
            'delivery_fee' => ['sometimes', 'required', 'integer', 'min:0'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = $validator->validated()['items'];
            $products = Product::query()->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');

            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id']);

                if (! $product || ! $product->is_active) {
                    $validator->errors()->add("items.$index.product_id", 'Select an active product.');

                    continue;
                }

                if (mb_strlen($product->sku) > 100) {
                    $validator->errors()->add("items.$index.product_id", 'The product SKU must not exceed 100 characters.');
                }

                if ($product->selling_price < 0) {
                    $validator->errors()->add("items.$index.product_id", 'The product price must be non-negative.');
                }
            }
        }];
    }
}
