<?php

namespace App\Http\Requests\Api\V1\Listing;

use App\Enums\ListingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update: send only the fields that should change.
 */
class UpdateListingRequest extends FormRequest
{
    /**
     * The take-home scope has no authentication, so the endpoint stays open.
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
            'agent_id' => ['sometimes', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type' => ['sometimes', Rule::enum(ListingType::class)],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999.99'],
            'bedrooms' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
        ];
    }
}
