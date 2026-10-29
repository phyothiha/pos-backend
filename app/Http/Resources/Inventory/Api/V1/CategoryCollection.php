<?php

namespace App\Http\Resources\Inventory\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;

class CategoryCollection extends ResourceCollection
{
    /**
     * Customize the pagination information for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $paginated
     * @param  array  $default
     * @return array
     */
    public function paginationInformation($request, $paginated, $default)
    {
        return [
            'meta' => Arr::only($paginated, [
                'current_page',
                'from',
                'last_page',
                'per_page',
                'to',
                'total',
            ]),
        ];
    }
}
