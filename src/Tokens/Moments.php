<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * A moment as the package binds it into one of its own columns, to write it or to compare a column with it.
 *
 * The package keeps its moments with their zone, `timestamptz` on PostgreSQL. Laravel binds a date as its wall-clock
 * reading without an offset, and PostgreSQL reads such a reading in the time zone of the connection's session, which
 * Laravel sets only where the connection names a `timezone`. In a session of another zone than the application's, a
 * moment would be stored that far from itself, and the occasion of an alert would never match the expiry Sanctum
 * holds. On PostgreSQL the reading therefore carries its offset, and the column holds the moment whatever the
 * session's zone. MySQL and SQLite keep the reading Laravel binds, in the application's zone.
 *
 * @internal
 */
final class Moments
{
    /** `$moment` bound for one of the package's columns on the default connection. */
    public static function bound(DateTimeInterface $moment): DateTimeInterface|string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? $moment->format('Y-m-d H:i:sP') : $moment;
    }
}
