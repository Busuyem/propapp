<?php

namespace App\Http\Requests\Api\V1\Listing;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SearchListingRequest extends ListingFilterRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'sort' => ['sometimes', Rule::in(['newest', 'price_asc', 'price_desc', 'distance'])],
            // These three rules together make the location filters all-or-nothing.
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude,radius'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude,radius'],
            'radius' => ['nullable', 'numeric', 'gt:0', 'max:1000', 'required_with:latitude,longitude'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('sort') === 'distance' && ! $this->filled('latitude')) {
                    $validator->errors()->add('sort', 'Sorting by distance requires a latitude, longitude and radius.');
                }
            },
        ];
    }
}
