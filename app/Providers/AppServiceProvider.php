<?php

namespace App\Providers;

use App\Interfaces\ListingRepositoryInterface;
use App\Repositories\ListingRepository;
use App\Services\Geo\SqliteMathFunctions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->app->bind(ListingRepositoryInterface::class, ListingRepository::class);
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Hand SQLite the trigonometry functions the radius search relies on, so
        // one search query serves MySQL in production and SQLite in tests.
        Event::listen(
            ConnectionEstablished::class,
            fn (ConnectionEstablished $event) => SqliteMathFunctions::registerFor($event->connection),
        );
    }
}
