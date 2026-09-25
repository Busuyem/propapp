<?php

namespace App\Services\Geo;

use Illuminate\Database\Connection;
use PDO;

/**
 * Registers the scalar functions the radius search relies on when the
 * application is running on SQLite.
 *
 * MySQL ships radians(), sin(), cos(), acos(), least() and greatest(), but the
 * SQLite build bundled with PHP is compiled without its optional math
 * functions. Registering equivalents here keeps a single search query for every
 * driver, so the test suite exercises exactly what production runs.
 */
final class SqliteMathFunctions
{
    /**
     * Register the functions on a connection, if that connection is SQLite.
     */
    public static function registerFor(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();

        if (! $pdo instanceof PDO) {
            return;
        }

        $pdo->sqliteCreateFunction('radians', fn (float $degrees): float => deg2rad($degrees), 1);
        $pdo->sqliteCreateFunction('sin', fn (float $radians): float => sin($radians), 1);
        $pdo->sqliteCreateFunction('cos', fn (float $radians): float => cos($radians), 1);
        $pdo->sqliteCreateFunction('acos', self::acos(...), 1);
        $pdo->sqliteCreateFunction('least', fn (float $left, float $right): float => min($left, $right), 2);
        $pdo->sqliteCreateFunction('greatest', fn (float $left, float $right): float => max($left, $right), 2);
    }

    /**
     * acos() is undefined outside [-1, 1]; floating point error is clamped away.
     */
    private static function acos(float $cosine): float
    {
        return acos(min(1.0, max(-1.0, $cosine)));
    }
}
