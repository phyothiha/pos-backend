<?php

namespace App\Http\Requests\Inventory\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'required', 'integer', 'exists:brands,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'selling_price' => ['sometimes', 'required', 'integer', 'min:0'],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:2147483647'],
            'type' => ['sometimes', 'required', Rule::in(['in_stock', 'pre_order'])],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
