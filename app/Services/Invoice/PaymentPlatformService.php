<?php

namespace App\Services\Invoice;

use App\Models\Invoice\PaymentPlatform;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PaymentPlatformService
{
    public function all(): Collection
    {
        return PaymentPlatform::query()->orderBy('name')->get();
    }

    public function paginate(): LengthAwarePaginator
    {
        return PaymentPlatform::query()->orderBy('name')->paginate(15);
    }

    /** @param array{name: string} $data */
    public function create(array $data): PaymentPlatform
    {
        return PaymentPlatform::query()->create($data);
    }
}
