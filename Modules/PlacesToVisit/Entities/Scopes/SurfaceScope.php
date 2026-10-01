<?php

namespace Modules\PlacesToVisit\Entities\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\PlacesToVisit\Entities\Place;

/**
 * Keeps non-Spots places (vet clinics, `surface = pets`) out of Spots.
 *
 * Clinics live in `places` because every field the clinic sheet needs is
 * already there. But Spots reads places from many places of its own: the list,
 * details, leaderboard, trending, the weekly winner, the prize draw and its
 * pushes. Excluding clinics at each of those sites means the next one written
 * leaks them. So the exclusion is the default and the pets endpoint opts out
 * (`Place::withoutGlobalScope(SurfaceScope::class)`), not the other way round.
 *
 * The admin panel sees everything: it is where clinics are created and edited.
 */
class SurfaceScope implements Scope
{
    public const SPOTS = 'spots';
    public const PETS = 'pets';

    public function apply(Builder $builder, Model $model): void
    {
        if (self::isAdminRequest()) {
            return;
        }

        if ($model instanceof Place) {
            $builder->whereIn($model->qualifyColumn('category_id'), function ($query) {
                $query->select('id')->from('place_categories')->where('surface', self::SPOTS);
            });
            return;
        }

        $builder->where($model->qualifyColumn('surface'), self::SPOTS);
    }

    private static function isAdminRequest(): bool
    {
        return !app()->runningInConsole() && request()->is('admin/*');
    }
}
