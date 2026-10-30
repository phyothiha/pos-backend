<?php

namespace App\Http\Requests\Invoice\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'payment_status' => ['required', Rule::in(['paid'])],
            'payment_platform_id' => ['sometimes', 'nullable', 'integer', 'exists:payment_platforms,id'],
        ];
    }
}
