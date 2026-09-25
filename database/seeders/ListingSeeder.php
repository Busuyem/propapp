<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    /**
     * Seed a handful of agents with listings spread across Lagos and Abuja.
     */
    public function run(): void
    {
        $agents = User::factory()->count(5)->create();

        Listing::factory()->count(40)->inLagos()->recycle($agents)->create();
        Listing::factory()->count(20)->inAbuja()->recycle($agents)->create();
    }
}
