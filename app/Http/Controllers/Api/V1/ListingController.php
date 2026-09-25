<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Listing\IndexListingRequest;
use App\Http\Requests\Api\V1\Listing\StoreListingRequest;
use App\Http\Requests\Api\V1\Listing\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ListingController extends Controller
{
    public function __construct(private readonly ListingService $listings) {}

    /**
     * List listings, newest first.
     */
    public function index(IndexListingRequest $request): AnonymousResourceCollection
    {
        return ListingResource::collection($this->listings->search($request->validated()));
    }

    /**
     * Create a listing.
     */
    public function store(StoreListingRequest $request): JsonResponse
    {
        $listing = $this->listings->create($request->validated());

        return ListingResource::make($listing)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a single listing.
     */
    public function show(Listing $listing): ListingResource
    {
        return ListingResource::make($listing);
    }

    /**
     * Update a listing; send only the fields that should change.
     */
    public function update(UpdateListingRequest $request, Listing $listing): ListingResource
    {
        return ListingResource::make($this->listings->update($listing, $request->validated()));
    }

    /**
     * Delete a listing.
     */
    public function destroy(Listing $listing): JsonResponse
    {
        $this->listings->delete($listing);

        return response()->json(['message' => 'Listing deleted successfully.']);
    }
}
