<?php

namespace App\Services;

use App\Enums\ListingType;
use App\Interfaces\ListingRepositoryInterface;
use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Application layer for listings.
 *
 * Controllers hand over validated input only; this class gives it the shape the
 * repository expects, applies the rules that are not expressible as validation
 * rules (page size limits, default ordering) and is the seam for anything the
 * operations grow later, such as authorization, events or cache invalidation.
 */
class ListingService
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public function __construct(private readonly ListingRepositoryInterface $listings) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Listing
    {
        return $this->listings->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Listing $listing, array $data): Listing
    {
        return $this->listings->update($listing, $data);
    }

    public function delete(Listing $listing): void
    {
        $this->listings->delete($listing);
    }

    /**
     * @param  array<string, mixed>  $filters  validated request input
     * @return LengthAwarePaginator<int, Listing>
     */
    public function search(array $filters): LengthAwarePaginator
    {
        return $this->listings->search($this->toSearchCriteria($filters));
    }

    /**
     * Map validated request input onto the repository's criteria shape.
     *
     * A radius only means something with a centre point, so a partial set of
     * location filters is dropped rather than half applied.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function toSearchCriteria(array $filters): array
    {
        $latitude = isset($filters['latitude']) ? (float) $filters['latitude'] : null;
        $longitude = isset($filters['longitude']) ? (float) $filters['longitude'] : null;
        $radiusKm = isset($filters['radius']) ? (float) $filters['radius'] : null;

        if ($latitude === null || $longitude === null || $radiusKm === null) {
            $latitude = $longitude = $radiusKm = null;
        }

        return [
            'type' => isset($filters['type']) ? ListingType::from($filters['type']) : null,
            'min_price' => isset($filters['min_price']) ? (float) $filters['min_price'] : null,
            'max_price' => isset($filters['max_price']) ? (float) $filters['max_price'] : null,
            'bedrooms' => isset($filters['bedrooms']) ? (int) $filters['bedrooms'] : null,
            'min_bedrooms' => isset($filters['min_bedrooms']) ? (int) $filters['min_bedrooms'] : null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_km' => $radiusKm,
            'sort' => $filters['sort'] ?? 'newest',
            'per_page' => $this->perPage($filters),
            'page' => isset($filters['page']) ? (int) $filters['page'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        return max(1, min(self::MAX_PER_PAGE, $perPage));
    }
}
