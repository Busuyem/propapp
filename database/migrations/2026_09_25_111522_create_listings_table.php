<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['rent', 'sale', 'shortlet']);
            $table->string('address')->nullable();

            $table->decimal('price', 15, 2);
            $table->unsignedTinyInteger('bedrooms');

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->timestamps();

            // Backs the type/price filters used by list and search requests.
            $table->index(['type', 'price']);
            $table->index('bedrooms');

            // Backs the bounding box pre-filter used by the radius search.
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
