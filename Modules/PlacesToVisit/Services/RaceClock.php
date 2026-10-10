<?php

namespace Modules\PlacesToVisit\Services;

use Illuminate\Support\Carbon;

/**
 * The race runs on neighborhood time, not server time.
 *
 * app.timezone is UTC (and must stay UTC — order timestamps depend on it),
 * but the weekly voting period has to flip at Friday 00:00 *Cairo* (the start
 * of the Egyptian weekend) so the app's countdown, the close cron, and the
 * period stamp all agree.
 *
 * Periods keep the ISO `YYYY-Www` label. The clock is shifted forward three
 * days before labelling, so Friday 00:00 lands on Monday 00:00 and the ISO
 * week flips exactly at the Friday lock. A period runs Fri 00:00 → Thu 23:59
 * and carries the ISO week of its Monday–Thursday. Existing rows stay valid.
 */
class RaceClock
{
    /** Days between the Friday lock and the ISO Monday boundary. */
    private const SHIFT_DAYS = 3;

    public static function timezone(): string
    {
        return config('placestovisit.timezone', 'Africa/Cairo');
    }

    public static function now(): Carbon
    {
        return now(self::timezone());
    }

    /** `$at` moved onto the ISO grid, where the race boundary is Monday 00:00. */
    private static function shifted(?Carbon $at = null): Carbon
    {
        return ($at ?? self::now())->copy()->addDays(self::SHIFT_DAYS);
    }

    /** Current voting period, e.g. 2026-W28 */
    public static function period(): string
    {
        return self::shifted()->format('o-\WW');
    }

    /** The period `$weeksAgo` rounds before the current one. */
    public static function periodWeeksAgo(int $weeksAgo): string
    {
        return self::shifted()->subWeeks($weeksAgo)->format('o-\WW');
    }

    /** Most recent fully-ended period */
    public static function lastClosedPeriod(): string
    {
        return self::periodWeeksAgo(1);
    }

    /** When the current week locks (next Friday 00:00 Cairo) */
    public static function lockTime(): Carbon
    {
        return self::shifted()->startOfWeek()->addWeek()->subDays(self::SHIFT_DAYS);
    }
}
