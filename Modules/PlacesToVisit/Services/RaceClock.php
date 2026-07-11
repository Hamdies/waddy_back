<?php

namespace Modules\PlacesToVisit\Services;

use Illuminate\Support\Carbon;

/**
 * The race runs on neighborhood time, not server time.
 *
 * app.timezone is UTC (and must stay UTC — order timestamps depend on it),
 * but the weekly voting period has to flip at Monday 00:00 *Cairo* so the
 * app's countdown, the close cron, and the period stamp all agree.
 */
class RaceClock
{
    public static function timezone(): string
    {
        return config('placestovisit.timezone', 'Africa/Cairo');
    }

    public static function now(): Carbon
    {
        return now(self::timezone());
    }

    /** Current voting period, e.g. 2026-W28 */
    public static function period(): string
    {
        return self::now()->format('o-\WW');
    }

    /** Most recent fully-ended period (last ISO week) */
    public static function lastClosedPeriod(): string
    {
        return self::now()->subWeek()->format('o-\WW');
    }

    /** When the current week locks (next Monday 00:00 Cairo) */
    public static function lockTime(): Carbon
    {
        return self::now()->startOfWeek()->addWeek();
    }
}
