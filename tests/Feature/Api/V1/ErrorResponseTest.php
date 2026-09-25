<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_missing_listing_returns_a_clean_404_envelope(): void
    {
        $this->getJson('/api/v1/listings/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'The requested resource was not found.']);
    }

    public function test_an_unknown_endpoint_returns_a_clean_404_envelope(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertNotFound()
            ->assertExactJson(['message' => 'The requested endpoint was not found.']);
    }

    public function test_an_unsupported_method_returns_405_with_the_allow_header(): void
    {
        $this->deleteJson('/api/v1/listings')
            ->assertMethodNotAllowed()
            ->assertHeader('Allow')
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace');
    }

    public function test_a_validation_failure_returns_errors_without_internals(): void
    {
        $this->postJson('/api/v1/listings', ['type' => 'castle'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['type']])
            ->assertJsonPath('errors.type.0', 'The selected type is invalid.')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace');
    }

    public function test_a_server_error_hides_internals_when_debug_is_off(): void
    {
        Exceptions::fake();
        config(['app.debug' => false]);
        $this->registerFailingRoute();

        $this->getJson('/api/v1/boom')
            ->assertServerError()
            ->assertExactJson(['message' => 'Server Error.']);

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_a_server_error_exposes_a_debug_block_when_debug_is_on(): void
    {
        Exceptions::fake();
        config(['app.debug' => true]);
        $this->registerFailingRoute();

        $this->getJson('/api/v1/boom')
            ->assertServerError()
            ->assertJsonPath('message', 'Server Error.')
            ->assertJsonPath('debug.exception', RuntimeException::class)
            ->assertJsonPath('debug.message', 'Something went wrong.');
    }

    private function registerFailingRoute(): void
    {
        Route::get('api/v1/boom', fn () => throw new RuntimeException('Something went wrong.'));
    }
}
