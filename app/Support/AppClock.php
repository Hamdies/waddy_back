<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Single source of truth for "app-local" day and week boundaries.
 *
 * app.timezone is UTC and must stay UTC — order/wallet timestamps depend on
 * it. But user-facing cadence (daily & weekly challenges, streaks, the Places
 * race) has to flip on neighborhood time so the app's countdowns, the reset
 * crons, and the period stamps all agree. That local timezone and the
 * Monday-anchored ISO week live here, so every consumer resets on the same
 * boundary instead of each picking its own (isToday / weekOfYear / Cairo).
 *
 * Kept consistent with the Places module's RaceClock (Monday 00:00 Cairo),
 * which the weekly voting round already runs on.
 */
class AppClock
{
    public static function timezone(): string
    {
        return config('app.local_timezone', 'Africa/Cairo');
    }

    /** Current moment in app-local time. */
    public static function now(): Carbon
    {
        return now(self::timezone());
    }

    /** Start of today in app-local time. */
    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    /** Whether a timestamp falls on today (app-local). */
    public static function isToday($date): bool
    {
        if (!$date) {
            return false;
        }
        return Carbon::parse($date)->timezone(self::timezone())->isSameDay(self::now());
    }

    /** Whether a timestamp falls on the same app-local day as today minus one. */
    public static function wasYesterday($date): bool
    {
        if (!$date) {
            return false;
        }
        return Carbon::parse($date)->timezone(self::timezone())
            ->isSameDay(self::now()->subDay());
    }

    /** Current weekly period key, e.g. 2026-W28 (Monday-anchored, ISO). */
    public static function weekPeriod(): string
    {
        return self::now()->format('o-\WW');
    }

    /** Current monthly period key, e.g. 2026-07. */
    public static function monthPeriod(): string
    {
        return self::now()->format('Y-m');
    }

    /** Period key for the given season type ('weekly' | 'monthly'). */
    public static function periodFor(string $periodType): string
    {
        return $periodType === 'monthly' ? self::monthPeriod() : self::weekPeriod();
    }

    /** Whether two timestamps fall in the same app-local ISO week. */
    public static function sameWeek($a, $b): bool
    {
        if (!$a || !$b) {
            return false;
        }
        $tz = self::timezone();
        return Carbon::parse($a)->timezone($tz)->format('o-\WW')
            === Carbon::parse($b)->timezone($tz)->format('o-\WW');
    }
}
