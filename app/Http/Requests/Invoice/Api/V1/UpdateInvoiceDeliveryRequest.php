<?php

namespace App\Http\Requests\Invoice\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'delivery_status' => ['required', Rule::in(['pending', 'preparing', 'ready_for_dispatch', 'on_the_way', 'delivered', 'cancelled', 'failed_delivery', 'returned'])],
        ];
    }
}
