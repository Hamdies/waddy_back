<?php

namespace Modules\PlacesToVisit\Services;

use Illuminate\Support\Collection;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceWinner;
use Modules\PlacesToVisit\Entities\PlaceZone;

class WinnerService
{
    /**
     * Close a finished week: crown the overall winner plus one winner per
     * zone that had votes. Idempotent — a period already closed is skipped.
     *
     * @return Collection<PlaceWinner> the winners created (empty if none/closed)
     */
    public function closePeriod(?string $period = null): Collection
    {
        $period = $period ?? $this->lastClosedPeriod();

        // Never close the running week, and never close twice
        if ($period === \Modules\PlacesToVisit\Services\RaceClock::period() ||
            PlaceWinner::where('period', $period)->exists()) {
            return collect();
        }

        $created = collect();

        // Overall champion (zone_id null)
        if ($top = $this->topPlaceFor($period)) {
            $created->push(PlaceWinner::create([
                'period' => $period,
                'zone_id' => null,
                'place_id' => $top->id,
                'votes_count' => $top->votes_count,
                'avg_rating' => round($top->votes_avg_rating ?? 0, 1),
            ]));
        }

        // Per-zone champions
        foreach (PlaceZone::pluck('id') as $zoneId) {
            if ($top = $this->topPlaceFor($period, $zoneId)) {
                $created->push(PlaceWinner::create([
                    'period' => $period,
                    'zone_id' => $zoneId,
                    'place_id' => $top->id,
                    'votes_count' => $top->votes_count,
                    'avg_rating' => round($top->votes_avg_rating ?? 0, 1),
                ]));
            }
        }

        return $created;
    }

    /**
     * Latest overall winner (most recently closed week). Lazily closes the
     * previous week first, so winners appear even if the cron never ran.
     */
    public function latest(?int $zoneId = null): ?PlaceWinner
    {
        $this->closePeriod();

        return PlaceWinner::with(['place.translations', 'place.category', 'zone'])
            ->when($zoneId, fn($q) => $q->where('zone_id', $zoneId),
                fn($q) => $q->whereNull('zone_id'))
            ->orderByDesc('period')
            ->first();
    }

    /**
     * Hall of fame: past winners, newest first
     */
    public function history(?int $zoneId = null, int $limit = 24): Collection
    {
        $this->closePeriod();

        return PlaceWinner::with(['place.translations', 'place.category', 'zone'])
            ->when($zoneId, fn($q) => $q->where('zone_id', $zoneId),
                fn($q) => $q->whereNull('zone_id'))
            ->orderByDesc('period')
            ->limit($limit)
            ->get();
    }

    /**
     * The most recent period that has fully ended (last ISO week)
     */
    public function lastClosedPeriod(): string
    {
        return RaceClock::lastClosedPeriod();
    }

    /**
     * Top-voted place for a period (min 1 vote), ties broken by rating
     */
    protected function topPlaceFor(string $period, ?int $zoneId = null): ?Place
    {
        return Place::query()
            ->active()
            ->when($zoneId, fn($q) => $q->where('zone_id', $zoneId))
            ->withCount(['votes' => fn($q) => $q->where('period', $period)])
            ->withAvg(['votes' => fn($q) => $q->where('period', $period)->whereNotNull('rating')], 'rating')
            ->having('votes_count', '>=', 1)
            ->orderByDesc('votes_count')
            ->orderByDesc('votes_avg_rating')
            ->first();
    }

    /**
     * Serialize a winner row for the API
     */
    public function toApiPayload(PlaceWinner $winner): array
    {
        $place = $winner->place;

        return [
            'id' => $winner->id,
            'period' => $winner->period,
            'zone_id' => $winner->zone_id,
            'zone' => $winner->zone?->display_name,
            'votes_count' => $winner->votes_count,
            'avg_rating' => $winner->avg_rating,
            'titles_count' => PlaceWinner::titleCount($winner->place_id),
            'place' => $place ? [
                'id' => $place->id,
                'title' => $place->title,
                'image' => $place->image,
                'cover_image' => $place->cover_image,
                'category' => $place->category?->name,
            ] : null,
        ];
    }
}
