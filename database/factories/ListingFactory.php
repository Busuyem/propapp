<?php

namespace Database\Factories;

use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bedrooms = fake()->numberBetween(1, 6);

        return [
            'agent_id' => User::factory(),
            'title' => sprintf(
                '%d bedroom %s in %s',
                $bedrooms,
                fake()->randomElement(['flat', 'duplex', 'bungalow', 'terrace']),
                fake()->randomElement(['Lekki', 'Ikeja', 'Yaba', 'Ajah', 'Gwarinpa', 'Wuse 2']),
            ),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(ListingType::cases()),
            'address' => fake()->streetAddress(),
            'price' => fake()->numberBetween(500_000, 250_000_000),
            'bedrooms' => $bedrooms,
            'latitude' => fake()->latitude(6.30, 6.70),
            'longitude' => fake()->longitude(3.20, 3.70),
        ];
    }

    /**
     * Pin the listing to a specific listing type.
     */
    public function ofType(ListingType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    /**
     * Pin the listing to an exact coordinate.
     */
    public function locatedAt(float $latitude, float $longitude): static
    {
        return $this->state(fn (): array => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Place the listing at a random point inside a latitude/longitude box.
     */
    public function locatedWithin(float $minLatitude, float $maxLatitude, float $minLongitude, float $maxLongitude): static
    {
        return $this->state(fn (): array => [
            'latitude' => fake()->latitude($minLatitude, $maxLatitude),
            'longitude' => fake()->longitude($minLongitude, $maxLongitude),
        ]);
    }

    /**
     * A listing somewhere in the Lagos area.
     */
    public function inLagos(): static
    {
        return $this->locatedWithin(6.35, 6.70, 3.20, 3.70);
    }

    /**
     * A listing somewhere in the Abuja area.
     */
    public function inAbuja(): static
    {
        return $this->locatedWithin(8.95, 9.15, 7.30, 7.60);
    }
}
