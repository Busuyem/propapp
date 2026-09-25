<?php

namespace App\Http\Requests\Api\V1\Listing;

use App\Enums\ListingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filters shared by the list and search endpoints.
 *
 * Subclasses add their own ordering options and, for search, the location
 * filters needed for a radius query.
 */
abstract class ListingFilterRequest extends FormRequest
{
    /**
     * The take-home scope has no authentication, so the endpoints stay open.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(ListingType::class)],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'bedrooms' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'min_bedrooms' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Reject an inverted price range once both bounds are individually valid.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['min_price', 'max_price'])) {
                    return;
                }

                if (! $this->filled('min_price') || ! $this->filled('max_price')) {
                    return;
                }

                if ((float) $this->input('max_price') < (float) $this->input('min_price')) {
                    $validator->errors()->add('max_price', 'The max price must be greater than or equal to the min price.');
                }
            },
        ];
    }
}
