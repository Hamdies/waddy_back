<?php

namespace Modules\PlacesToVisit\Services;

use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\PlacesToVisit\Entities\Place;

/**
 * Race pushes — exactly two triggers, no spam:
 *  1. Lead change (checked after every vote, 30-min cooldown per scope)
 *  2. Final-hours close race (scheduled Sunday evening)
 *
 * Topics: places_race_all + places_race_zone_{id} (app subscribes on the
 * Spots screen; zone topic follows the selected zone filter).
 */
class PlacePushService
{
    public const TOPIC_ALL = 'places_race_all';
    protected const COOLDOWN_MINUTES = 30;

    public static function zoneTopic(int $zoneId): string
    {
        return "places_race_zone_{$zoneId}";
    }

    /**
     * Called after any vote mutation. Checks the place's zone scope and the
     * overall scope for a #1 change and pushes when the throne flips.
     */
    public function checkLeadChange(int $placeId): void
    {
        try {
            $zoneId = Place::where('id', $placeId)->value('zone_id');
            $this->checkScope(null);
            if ($zoneId) {
                $this->checkScope((int) $zoneId);
            }
        } catch (\Throwable $e) {
            Log::warning('places lead-change push failed: ' . $e->getMessage());
        }
    }

    protected function checkScope(?int $zoneId): void
    {
        $period = now()->format('o-\WW');
        $scope = $zoneId ?? 'all';

        $leader = $this->topTwo($period, $zoneId)->first();
        if (!$leader) {
            return;
        }

        $key = "places_leader:{$period}:{$scope}";
        $previous = Cache::get($key); // ['id' => .., 'title' => ..]
        Cache::put($key, ['id' => $leader->id, 'title' => $leader->title], now()->addWeek());

        if (!$previous || $previous['id'] === $leader->id) {
            return; // first leader of the week, or no change
        }

        // Cooldown so a seesaw race doesn't spam the topic
        $cooldownKey = "places_leadpush:{$period}:{$scope}";
        if (Cache::has($cooldownKey)) {
            return;
        }
        Cache::put($cooldownKey, 1, now()->addMinutes(self::COOLDOWN_MINUTES));

        $hoursLeft = max(1, (int) now()->diffInHours(now()->startOfWeek()->addWeek()));
        Helpers::send_push_notif_to_topic(
            data: [
                'title' => '🚨 New leader in the race!',
                'description' => "{$leader->title} just took #1 from {$previous['title']} — {$hoursLeft}h until the crown locks. Defend your spot!",
                'image' => '',
            ],
            topic: $zoneId ? self::zoneTopic($zoneId) : self::TOPIC_ALL,
            type: 'general'
        );
    }

    /**
     * Sunday-evening nudge for every scope where the race is within reach.
     * Returns the number of pushes sent (for the console command).
     */
    public function sendFinalHoursPushes(int $maxGap = 3): int
    {
        $period = now()->format('o-\WW');
        $sent = 0;

        $scopes = [null, ...\Modules\PlacesToVisit\Entities\PlaceZone::pluck('id')->all()];
        foreach ($scopes as $zoneId) {
            $top = $this->topTwo($period, $zoneId);
            if ($top->count() < 2) {
                continue;
            }
            [$first, $second] = [$top[0], $top[1]];
            $gap = $first->votes_count - $second->votes_count;
            if ($gap > $maxGap) {
                continue;
            }

            $gapText = $gap === 0
                ? "{$first->title} and {$second->title} are TIED"
                : "{$first->title} leads {$second->title} by {$gap} vote" . ($gap === 1 ? '' : 's');

            Helpers::send_push_notif_to_topic(
                data: [
                    'title' => '⏰ Final hours — the crown is up for grabs!',
                    'description' => "{$gapText}. Voting locks at midnight — your vote decides it.",
                    'image' => '',
                ],
                topic: $zoneId ? self::zoneTopic($zoneId) : self::TOPIC_ALL,
                type: 'general'
            );
            $sent++;
        }

        return $sent;
    }

    protected function topTwo(string $period, ?int $zoneId)
    {
        return Place::query()
            ->active()
            ->when($zoneId, fn($q) => $q->where('zone_id', $zoneId))
            ->withCount(['votes' => fn($q) => $q->where('period', $period)])
            ->having('votes_count', '>=', 1)
            ->orderByDesc('votes_count')
            ->limit(2)
            ->get();
    }
}
