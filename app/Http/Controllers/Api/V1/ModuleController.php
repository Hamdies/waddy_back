<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Zone;
use Illuminate\Http\Request;


class ModuleController extends Controller
{

    public function index(Request $request)
    {
        $zone_id = json_decode($request->header('zoneId'), true);
        $zone_id = is_array($zone_id) ? $zone_id : ($zone_id === null ? [] : [$zone_id]);
        // Drop nulls so a malformed header can't become whereIn('zone_id', [null]).
        $zone_id = array_values(array_filter($zone_id, fn($id) => $id !== null));

        // An empty zone list means the client is outside every serving zone
        // (or hasn't resolved one yet). Filtering on it would match nothing and
        // hand back an empty catalogue — "No module found" — so fall through to
        // the unfiltered branch and let them browse. Delivery is still gated
        // client-side at checkout.
        if (count($zone_id) > 0) {
            $modules = Module::with('zones')
                ->withCount([
                    'items',
                    'stores' => function ($query) use ($zone_id) {
                        $query->whereIn('zone_id', $zone_id);
                    }
                ])
                ->whereHas('zones', function ($query) use ($zone_id) {
                    $query->whereIn('zone_id', $zone_id);
                })
                ->active()
                ->get();
        } else {
            $modules = Module::withCount([
                'items',
                'stores' => function ($query) use ($request) {
                    $query->when($request->zone_id, function ($q) use ($request) {
                        $q->where('zone_id', $request->zone_id);
                    });
                }
            ])
            ->when($request->zone_id, function ($query) use ($request) {
                $query->whereHas('zones', function ($query) use ($request) {
                    $query->where('zone_id', $request->zone_id);
                })->notParcel();
            })
            ->active()
            ->get();
        }

        $modules = array_map(function($item){
            return $item;
        },$modules->toArray());
        return response()->json($modules);
    }

}
