<?php

namespace App\Services\Geo;

/**
 * Spherical geometry behind the radius search.
 *
 * The same SQL expression is emitted for every database driver, so the query
 * exercised by the test suite is the query that runs in production. MySQL
 * ships the trigonometry functions; SQLite needs them registered first, see
 * {@see SqliteMathFunctions}.
 */
final class GeoMath
{
    /**
     * Mean radius of the earth in kilometres.
     */
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Great-circle distance in kilometres between two coordinates.
     */
    public static function distanceKm(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
    ): float {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($haversine)));
    }

    /**
     * Bounds of the square that encloses a radius circle.
     *
     * This is the cheap, index-backed pre-filter: the exact distance is still
     * checked with the haversine expression, but the database only has to
     * consider rows inside the box instead of the whole table.
     *
     * @return array{min_latitude: float, max_latitude: float, min_longitude: float, max_longitude: float}
     */
    public static function boundingBox(float $latitude, float $longitude, float $radiusKm): array
    {
        $latitudeDelta = rad2deg($radiusKm / self::EARTH_RADIUS_KM);
        $longitudeDelta = self::longitudeDelta($latitude, $radiusKm);

        return [
            'min_latitude' => max(-90.0, $latitude - $latitudeDelta),
            'max_latitude' => min(90.0, $latitude + $latitudeDelta),
            'min_longitude' => max(-180.0, $longitude - $longitudeDelta),
            'max_longitude' => min(180.0, $longitude + $longitudeDelta),
        ];
    }

    /**
     * Haversine expression, in kilometres, for a pair of coordinate columns.
     *
     * The column names are interpolated because they are internal constants and
     * never request input. The expression needs three bindings; they are
     * returned alongside it so the two can never drift apart.
     *
     * @return array{0: string, 1: array<int, float>}
     */
    public static function distanceExpression(
        string $latitudeColumn,
        string $longitudeColumn,
        float $latitude,
        float $longitude,
    ): array {
        $expression = sprintf(
            '(%s * acos(least(1, greatest(-1, '
                .'cos(radians(?)) * cos(radians(%s)) * cos(radians(%s) - radians(?)) '
                .'+ sin(radians(?)) * sin(radians(%s))'
            .'))))',
            self::EARTH_RADIUS_KM,
            $latitudeColumn,
            $longitudeColumn,
            $latitudeColumn,
        );

        return [$expression, [$latitude, $longitude, $latitude]];
    }

    /**
     * Degrees of longitude covered by a radius at the given latitude.
     */
    private static function longitudeDelta(float $latitude, float $radiusKm): float
    {
        $offset = cos(deg2rad($latitude));

        if ($offset <= 0.0) {
            return 180.0;
        }

        return min(180.0, rad2deg($radiusKm / (self::EARTH_RADIUS_KM * $offset)));
    }
}
