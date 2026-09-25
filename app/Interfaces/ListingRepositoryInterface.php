<?php

namespace App\Interfaces;

use App\Enums\ListingType;
use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Persistence contract for property listings.
 *
 * Callers depend on this interface rather than on Eloquent, which keeps the
 * service layer free of query building and lets the storage strategy change
 * without touching business logic.
 */
interface ListingRepositoryInterface
{
    /**
     * Search listings using a fully normalised set of criteria.
     *
     * Every key is always present; unused filters are null. The "radius" keys
     * are all-or-nothing: when all three are null the search is not geographic.
     *
     * @param  array{
     *     type: ListingType|null,
     *     min_price: float|null,
     *     max_price: float|null,
     *     bedrooms: int|null,
     *     min_bedrooms: int|null,
     *     latitude: float|null,
     *     longitude: float|null,
     *     radius_km: float|null,
     *     sort: string,
     *     per_page: int,
     *     page: int|null,
     * }  $criteria
     * @return LengthAwarePaginator<int, Listing>
     */
    public function search(array $criteria): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Listing;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Listing $listing, array $attributes): Listing;

    public function delete(Listing $listing): bool;
}
