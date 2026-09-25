<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ListingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_listings_newest_first_with_pagination_meta(): void
    {
        $older = Listing::factory()->create(['created_at' => now()->subDay()]);
        $newer = Listing::factory()->create(['created_at' => now()]);

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_filters_by_type(): void
    {
        $rental = Listing::factory()->ofType(ListingType::Rent)->create();
        Listing::factory()->ofType(ListingType::Sale)->create();

        $this->getJson('/api/v1/listings?type=rent')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rental->id);
    }

    public function test_index_filters_by_price_range_and_bedroom_count(): void
    {
        $match = Listing::factory()->create(['price' => 5_000_000, 'bedrooms' => 3]);
        Listing::factory()->create(['price' => 50_000_000, 'bedrooms' => 3]);
        Listing::factory()->create(['price' => 5_000_000, 'bedrooms' => 5]);

        $this->getJson('/api/v1/listings?min_price=1000000&max_price=10000000&bedrooms=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_rejects_an_unknown_type_with_422(): void
    {
        $this->getJson('/api/v1/listings?type=castle')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_index_rejects_a_max_price_below_the_min_price_with_422(): void
    {
        $this->getJson('/api/v1/listings?min_price=500&max_price=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_price');
    }

    public function test_index_rejects_a_page_size_above_the_cap_with_422(): void
    {
        $this->getJson('/api/v1/listings?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_store_creates_a_listing_and_returns_201(): void
    {
        $agent = User::factory()->create();

        $this->postJson('/api/v1/listings', [
            'agent_id' => $agent->id,
            'title' => '3 bedroom flat in Lekki',
            'description' => 'Serviced flat with a sea view.',
            'type' => 'rent',
            'address' => 'Admiralty Way, Lekki',
            'price' => 2_500_000.50,
            'bedrooms' => 3,
            'latitude' => 6.4413,
            'longitude' => 3.4721,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', '3 bedroom flat in Lekki')
            ->assertJsonPath('data.type', 'rent')
            ->assertJsonPath('data.price', 2_500_000.5)
            ->assertJsonPath('data.bedrooms', 3)
            ->assertJsonPath('data.location.latitude', 6.4413)
            ->assertJsonPath('data.agent_id', $agent->id);

        $this->assertDatabaseHas('listings', [
            'agent_id' => $agent->id,
            'title' => '3 bedroom flat in Lekki',
            'type' => 'rent',
            'bedrooms' => 3,
        ]);
    }

    public function test_store_rejects_an_invalid_payload_with_422(): void
    {
        $this->postJson('/api/v1/listings', [
            'title' => '',
            'type' => 'castle',
            'price' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'type', 'price', 'agent_id', 'bedrooms', 'latitude', 'longitude']);

        $this->assertDatabaseCount('listings', 0);
    }

    public function test_store_rejects_an_unknown_agent_with_422(): void
    {
        $this->postJson('/api/v1/listings', [
            'agent_id' => 999,
            'title' => 'Ghost listing',
            'type' => 'sale',
            'price' => 100,
            'bedrooms' => 1,
            'latitude' => 0,
            'longitude' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agent_id');
    }

    public function test_store_rejects_coordinates_outside_the_valid_range_with_422(): void
    {
        $this->postJson('/api/v1/listings', [
            'agent_id' => User::factory()->create()->id,
            'title' => 'Off planet',
            'type' => 'sale',
            'price' => 100,
            'bedrooms' => 1,
            'latitude' => 91,
            'longitude' => 181,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_show_returns_a_single_listing(): void
    {
        $listing = Listing::factory()->create();

        $this->getJson("/api/v1/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $listing->id)
            ->assertJsonPath('data.title', $listing->title)
            ->assertJsonPath('data.type', $listing->type->value);
    }

    public function test_show_returns_404_for_an_unknown_listing(): void
    {
        $this->getJson('/api/v1/listings/999')->assertNotFound();
    }

    public function test_update_changes_only_the_submitted_fields(): void
    {
        $listing = Listing::factory()->create(['title' => 'Old title', 'bedrooms' => 2]);

        $this->patchJson("/api/v1/listings/{$listing->id}", ['title' => 'New title', 'bedrooms' => 4])
            ->assertOk()
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.bedrooms', 4)
            ->assertJsonPath('data.type', $listing->type->value);

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'title' => 'New title',
            'bedrooms' => 4,
        ]);
    }

    public function test_update_rejects_an_invalid_payload_with_422(): void
    {
        $listing = Listing::factory()->create(['title' => 'Old title']);

        $this->patchJson("/api/v1/listings/{$listing->id}", ['type' => 'castle'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseHas('listings', ['id' => $listing->id, 'title' => 'Old title']);
    }

    public function test_destroy_deletes_the_listing_and_returns_a_confirmation(): void
    {
        $listing = Listing::factory()->create();

        $this->deleteJson("/api/v1/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Listing deleted successfully.');

        $this->assertDatabaseMissing('listings', ['id' => $listing->id]);
    }

    public function test_destroy_returns_404_when_the_listing_is_already_gone(): void
    {
        $listing = Listing::factory()->create();

        $this->deleteJson("/api/v1/listings/{$listing->id}")->assertOk();

        $this->deleteJson("/api/v1/listings/{$listing->id}")->assertNotFound();
    }
}
