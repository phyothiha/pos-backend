<?php

namespace App\Http\Requests\Inventory\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'selling_price' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'type' => ['sometimes', 'required', Rule::in(['in_stock', 'pre_order'])],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
