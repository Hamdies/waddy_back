<?php

namespace App\Services;

use App\Support\AppClock;

use App\Models\Item;
use App\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Out of stock for a while (store app Menu). The store hides an item for
 * 2 hours, for the rest of today, or until it switches it back on; the
 * scheduler (`items:reopen`, every minute) shows the timed ones again.
 */
class ItemAvailabilityService
{
    public const FOR_OPTIONS = ['2h', 'today', 'manual'];

    public static function hide(Item $item, string $for = 'manual'): void
    {
        $item->status = 0;
        $item->unavailable_since = now();
        $item->available_again_at = self::until($for, now(), self::openings($item->store));
        $item->save();
    }

    public static function show(Item $item): void
    {
        $item->status = 1;
        $item->unavailable_since = null;
        $item->available_again_at = null;
        $item->save();
    }

    /**
     * When a hidden item comes back. "today" means the store's next opening
     * after today (local time), or tomorrow 06:00 when it has no schedule.
     * Pure, so it is tested.
     *
     * @param  array<int, string>  $openings  day of week (0 = Sunday) => earliest opening "H:i:s"
     */
    public static function until(string $for, Carbon $now, array $openings): ?Carbon
    {
        if ($for === '2h') {
            return $now->copy()->addHours(2);
        }
        if ($for !== 'today') {
            return null;
        }
        $tz = config('app.local_timezone', 'Africa/Cairo');
        $local = $now->copy()->setTimezone($tz)->startOfDay();
        for ($i = 1; $i <= 7; $i++) {
            $day = $local->copy()->addDays($i);
            if (isset($openings[$day->dayOfWeek])) {
                [$h, $m] = array_map('intval', explode(':', $openings[$day->dayOfWeek]));
                return $day->setTime($h, $m)->setTimezone(AppClock::storageTimezone());
            }
        }
        return $local->addDay()->setTime(6, 0)->setTimezone(AppClock::storageTimezone());
    }

    /** @return array<int, string> */
    private static function openings(?Store $store): array
    {
        $openings = [];
        foreach ($store?->schedules ?? [] as $schedule) {
            $day = (int) $schedule->day;
            if (!isset($openings[$day]) || $schedule->opening_time < $openings[$day]) {
                $openings[$day] = $schedule->opening_time;
            }
        }
        return $openings;
    }

    /** Scheduler: show items whose time is up. */
    public static function reopenDue(): int
    {
        return Item::withoutGlobalScopes()
            ->whereNotNull('available_again_at')
            ->where('available_again_at', '<=', now())
            ->update(['status' => 1, 'unavailable_since' => null, 'available_again_at' => null]);
    }
}
