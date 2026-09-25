<?php

namespace App\Models;

use App\Enums\ListingType;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agent_id',
        'title',
        'description',
        'type',
        'address',
        'price',
        'bedrooms',
        'latitude',
        'longitude',
    ];

    /**
     * The agent who published the listing.
     *
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ListingType::class,
            'price' => 'decimal:2',
            'bedrooms' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
