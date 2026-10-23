<?php

namespace App\Services\AdminPanel\Resources\Spots;

use Illuminate\Database\Query\Builder;
use Modules\PlacesToVisit\Entities\Scopes\SurfaceScope;

/** Shared joins for the Waddy Spots pages: the English title and the Spots surface (no vet clinics). */
trait SpotsQueries
{
    protected function joinPlace(Builder $q, string $placeId): Builder
    {
        return $this->placeDetails($q->join('places as p', 'p.id', '=', $placeId));
    }

    /** Category (Spots only), English title and zone of the `p` place. */
    protected function placeDetails(Builder $q): Builder
    {
        return $q->join('place_categories as pc', 'pc.id', '=', 'p.category_id')
            ->where('pc.surface', SurfaceScope::SPOTS)
            ->leftJoin('place_translations as pt', fn ($j) => $j->on('pt.place_id', '=', 'p.id')->where('pt.locale', 'en'))
            ->leftJoin('place_zones as pz', 'pz.id', '=', 'p.zone_id');
    }

    protected static function masked(?string $first, ?string $last): string
    {
        $initial = $last ? mb_substr($last, 0, 1) . '.' : '';
        return trim(($first ?? '') . ' ' . $initial);
    }
}
