<?php

namespace App\Http\Requests\Api\V1\Listing;

use Illuminate\Validation\Rule;

class IndexListingRequest extends ListingFilterRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'sort' => ['sometimes', Rule::in(['newest', 'price_asc', 'price_desc'])],
        ];
    }
}
