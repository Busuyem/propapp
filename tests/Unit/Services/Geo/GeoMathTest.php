<?php

namespace Tests\Unit\Services\Geo;

use App\Services\Geo\GeoMath;
use PHPUnit\Framework\TestCase;

class GeoMathTest extends TestCase
{
    public function test_one_degree_of_latitude_is_about_111_kilometres(): void
    {
        // A quarter of the earth's circumference over 90 degrees, with R = 6371 km.
        $this->assertEqualsWithDelta(111.1949, GeoMath::distanceKm(0.0, 0.0, 1.0, 0.0), 0.0001);
    }

    public function test_the_distance_from_a_point_to_itself_is_zero(): void
    {
        $this->assertSame(0.0, GeoMath::distanceKm(6.4413, 3.4721, 6.4413, 3.4721));
    }

    public function test_the_bounding_box_spans_the_radius_in_every_direction(): void
    {
        $bounds = GeoMath::boundingBox(latitude: 0.0, longitude: 0.0, radiusKm: 10.0);

        // 10 km expressed in degrees: 10 / 111.1949.
        $expectedDelta = 0.0899322;

        $this->assertEqualsWithDelta(-$expectedDelta, $bounds['min_latitude'], 0.000001);
        $this->assertEqualsWithDelta($expectedDelta, $bounds['max_latitude'], 0.000001);
        $this->assertEqualsWithDelta(-$expectedDelta, $bounds['min_longitude'], 0.000001);
        $this->assertEqualsWithDelta($expectedDelta, $bounds['max_longitude'], 0.000001);
    }

    public function test_the_bounding_box_never_escapes_the_globe(): void
    {
        $bounds = GeoMath::boundingBox(latitude: 89.9, longitude: 179.9, radiusKm: 500.0);

        $this->assertSame(90.0, $bounds['max_latitude']);
        $this->assertSame(180.0, $bounds['max_longitude']);
    }

    public function test_the_distance_expression_has_one_binding_per_placeholder(): void
    {
        [$expression, $bindings] = GeoMath::distanceExpression('latitude', 'longitude', 6.4413, 3.4721);

        $this->assertSame(substr_count($expression, '?'), count($bindings));
        $this->assertSame([6.4413, 3.4721, 6.4413], $bindings);
    }
}
