<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Listing\SearchListingRequest;
use App\Http\Resources\ListingResource;
use App\Services\ListingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListingSearchController extends Controller
{
    public function __construct(private readonly ListingService $listings) {}

    /**
     * Search listings by type, price, bedrooms and distance from a point.
     */
    public function __invoke(SearchListingRequest $request): AnonymousResourceCollection
    {
        return ListingResource::collection($this->listings->search($request->validated()));
    }
}
