<?php

namespace App\Repositories;

use App\Interfaces\ListingRepositoryInterface;
use App\Models\Listing;
use App\Services\Geo\GeoMath;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListingRepository implements ListingRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $criteria
     * @return LengthAwarePaginator<int, Listing>
     */
    public function search(array $criteria): LengthAwarePaginator
    {
        $query = Listing::query();

        $this->applyFilters($query, $criteria);
        $this->applyRadius($query, $criteria);
        $this->applySort($query, $criteria);

        return $query
            ->paginate(perPage: $criteria['per_page'], page: $criteria['page'])
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Listing
    {
        return Listing::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Listing $listing, array $attributes): Listing
    {
        $listing->update($attributes);

        return $listing;
    }

    public function delete(Listing $listing): bool
    {
        return (bool) $listing->delete();
    }

    /**
     * @param  Builder<Listing>  $query
     * @param  array<string, mixed>  $criteria
     */
    private function applyFilters(Builder $query, array $criteria): void
    {
        if ($criteria['type'] !== null) {
            $query->where('type', $criteria['type']);
        }

        if ($criteria['min_price'] !== null) {
            $query->where('price', '>=', $criteria['min_price']);
        }

        if ($criteria['max_price'] !== null) {
            $query->where('price', '<=', $criteria['max_price']);
        }

        if ($criteria['bedrooms'] !== null) {
            $query->where('bedrooms', $criteria['bedrooms']);
        }

        if ($criteria['min_bedrooms'] !== null) {
            $query->where('bedrooms', '>=', $criteria['min_bedrooms']);
        }
    }

    /**
     * Narrow the query to a bounding box, then keep only rows that really are
     * within the radius. The distance is also selected so the response can
     * report it without a second round trip.
     *
     * @param  Builder<Listing>  $query
     * @param  array<string, mixed>  $criteria
     */
    private function applyRadius(Builder $query, array $criteria): void
    {
        if (! $this->hasRadius($criteria)) {
            return;
        }

        $bounds = GeoMath::boundingBox($criteria['latitude'], $criteria['longitude'], $criteria['radius_km']);

        [$expression, $bindings] = GeoMath::distanceExpression(
            'latitude',
            'longitude',
            $criteria['latitude'],
            $criteria['longitude'],
        );

        $query
            ->select('listings.*')
            ->selectRaw("{$expression} as distance_km", $bindings)
            ->whereBetween('latitude', [$bounds['min_latitude'], $bounds['max_latitude']])
            ->whereBetween('longitude', [$bounds['min_longitude'], $bounds['max_longitude']])
            ->whereRaw("{$expression} <= ?", [...$bindings, $criteria['radius_km']]);
    }

    /**
     * @param  Builder<Listing>  $query
     * @param  array<string, mixed>  $criteria
     */
    private function applySort(Builder $query, array $criteria): void
    {
        match ($criteria['sort']) {
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'distance' => $this->hasRadius($criteria)
                ? $query->orderBy('distance_km')->orderBy('id')
                : $this->applyDefaultSort($query),
            default => $this->applyDefaultSort($query),
        };
    }

    /**
     * Newest first, with a unique tie-breaker so pages stay stable.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    private function applyDefaultSort(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private function hasRadius(array $criteria): bool
    {
        return $criteria['latitude'] !== null
            && $criteria['longitude'] !== null
            && $criteria['radius_km'] !== null;
    }
}
