<?php

namespace App\Http\Controllers\Admin\Zone;

use App\Http\Controllers\Controller;
use App\Models\ZoneRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Where should we launch next, and which store should we sign first?"
 *
 * That question is the entire reason the zone_requests table exists, so this
 * view answers it directly rather than dumping rows: demand grouped by nearest
 * zone, and within the top areas, the stores people actually tried to order
 * from.
 */
class ZoneRequestAdminController extends Controller
{
    public function index(Request $request): View
    {
        // Demand per area. `reachable` matters as much as the raw count: an
        // area with 200 requests but no push tokens can't be told when we
        // launch, so its effective value at launch is far lower.
        $byZone = ZoneRequest::query()
            ->select([
                'nearest_zone_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN has_push = 1 THEN 1 ELSE 0 END) as reachable'),
                DB::raw('SUM(CASE WHEN is_guest = 1 THEN 1 ELSE 0 END) as guests'),
                DB::raw('MAX(created_at) as latest'),
            ])
            ->with('zone:id,name')
            ->groupBy('nearest_zone_id')
            ->orderByDesc('total')
            ->paginate(25);

        // What they were doing when they hit the wall. `add_to_cart` is the
        // highest-intent signal in the set — they had picked a specific item.
        $bySource = ZoneRequest::query()
            ->select(['source', DB::raw('COUNT(*) as total')])
            ->groupBy('source')
            ->orderByDesc('total')
            ->pluck('total', 'source');

        // The merchant sign-up shortlist: stores people tried to order from
        // while out of zone.
        $byStore = ZoneRequest::query()
            ->select(['store_id', DB::raw('COUNT(*) as total')])
            ->whereNotNull('store_id')
            ->with('store:id,name')
            ->groupBy('store_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        // Audit tail. Inflated numbers here would steer real expansion spend,
        // so recent rows stay inspectable (IP + timestamp) rather than being
        // silently trusted.
        $recent = ZoneRequest::query()
            ->with('zone:id,name')
            ->latest()
            ->limit(50)
            ->get();

        return view('admin-views.zone.requests', compact('byZone', 'bySource', 'byStore', 'recent'));
    }
}
