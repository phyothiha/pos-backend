<?php

namespace App\Http\Requests\Invoice\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentPlatformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100', 'unique:payment_platforms,name']];
    }
}
