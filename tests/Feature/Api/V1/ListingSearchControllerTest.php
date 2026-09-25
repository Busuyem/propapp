<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ListingType;
use App\Models\Listing;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ListingSearchControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Lekki, Lagos: the centre point every test measures from.
     */
    private const CENTRE_LATITUDE = 6.4413;

    private const CENTRE_LONGITUDE = 3.4721;

    public function test_search_returns_only_listings_inside_the_radius(): void
    {
        $near = Listing::factory()->locatedAt(6.4500, 3.4800)->create();
        Listing::factory()->locatedAt(9.0765, 7.3986)->create();

        $this->getJson($this->searchUrl(radius: 10))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $near->id);
    }

    public function test_search_reports_the_distance_from_the_search_point(): void
    {
        // 0.0087 degrees of latitude is 0.97 km; 0.0079 degrees of longitude at
        // this latitude is 0.87 km, so the two points are about 1.3 km apart.
        Listing::factory()->locatedAt(6.4500, 3.4800)->create();

        $distance = $this->getJson($this->searchUrl(radius: 10))
            ->assertOk()
            ->json('data.0.distance_km');

        $this->assertEqualsWithDelta(1.3, $distance, 0.05);
    }

    public function test_search_orders_results_by_distance_when_asked(): void
    {
        $further = Listing::factory()->locatedAt(6.5000, 3.5300)->create();
        $closer = Listing::factory()->locatedAt(6.4450, 3.4750)->create();

        $this->getJson($this->searchUrl(radius: 20, extra: ['sort' => 'distance']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $closer->id)
            ->assertJsonPath('data.1.id', $further->id);
    }

    public function test_search_combines_the_radius_with_type_and_price_filters(): void
    {
        $match = Listing::factory()
            ->ofType(ListingType::Shortlet)
            ->locatedAt(6.4450, 3.4750)
            ->create(['price' => 150_000]);

        Listing::factory()->ofType(ListingType::Sale)->locatedAt(6.4450, 3.4750)->create(['price' => 150_000]);
        Listing::factory()->ofType(ListingType::Shortlet)->locatedAt(6.4450, 3.4750)->create(['price' => 900_000]);
        Listing::factory()->ofType(ListingType::Shortlet)->locatedAt(9.0765, 7.3986)->create(['price' => 150_000]);

        $this->getJson($this->searchUrl(radius: 10, extra: [
            'type' => 'shortlet',
            'min_price' => 100_000,
            'max_price' => 200_000,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_search_paginates_radius_results_without_repeating_listings(): void
    {
        Listing::factory()->count(7)->locatedWithin(6.44, 6.45, 3.47, 3.48)->create();

        $firstPage = $this->getJson($this->searchUrl(radius: 10, extra: ['per_page' => 3, 'sort' => 'distance']))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.total', 7)
            ->assertJsonPath('meta.last_page', 3);

        $secondPage = $this->getJson($this->searchUrl(radius: 10, extra: [
            'per_page' => 3,
            'page' => 2,
            'sort' => 'distance',
        ]))
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $firstPageIds = collect($firstPage->json('data'))->pluck('id');
        $secondPageIds = collect($secondPage->json('data'))->pluck('id');

        $this->assertEmpty($firstPageIds->intersect($secondPageIds)->all());
    }

    public function test_search_rejects_an_incomplete_location_with_422(): void
    {
        $this->getJson('/api/v1/listings/search?latitude=6.4413')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude', 'radius']);
    }

    public function test_search_rejects_distance_sorting_without_a_location_with_422(): void
    {
        $this->getJson('/api/v1/listings/search?sort=distance')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function searchUrl(float $radius, array $extra = []): string
    {
        return '/api/v1/listings/search?'.http_build_query([
            'latitude' => self::CENTRE_LATITUDE,
            'longitude' => self::CENTRE_LONGITUDE,
            'radius' => $radius,
            ...$extra,
        ]);
    }
}
