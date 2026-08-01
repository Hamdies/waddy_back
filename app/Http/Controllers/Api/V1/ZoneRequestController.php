<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\ZoneRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * Demand capture for un-served areas.
 *
 * Public and unauthenticated by design: guests are the majority of out-of-zone
 * traffic, and requiring an account here would filter out exactly the people
 * whose interest we're trying to measure.
 */
class ZoneRequestController extends Controller
{
    /**
     * Record a "launch here" request.
     *
     * Idempotent per requester: re-tapping (e.g. on store A, then store B in
     * one session) UPDATES the existing row rather than inserting, so the
     * latest intent wins and the count stays honest. There is deliberately no
     * per-identity time throttle — it would reject that second tap before the
     * update ran, quietly breaking the refresh, and it wouldn't stop a spam
     * script anyway since minting a fresh guest id is the cheap part. Volume
     * abuse is IP-shaped and handled by the route middleware.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'source' => 'required|string|in:home_banner,add_to_cart,checkout,cart_proceed,sheet',
            'guest_id' => 'nullable',
            'address' => 'nullable|string|max:1000',
            'store_id' => 'nullable|integer',
            'module_id' => 'nullable|integer',
            'fcm_token' => 'nullable|string|max:512',
            'has_push' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        // Optional-auth pattern used across the guest-capable endpoints: the
        // middleware populates $request->user when a token is present.
        $user = $request->user instanceof \App\Models\User ? $request->user : null;
        $requester_id = $user ? $user->id : $request['guest_id'];
        $is_guest = $user ? 0 : 1;

        // Without an identity there's no dedupe key, so a single person could
        // inflate an area's count indefinitely. Reject rather than store noise.
        if (!$requester_id) {
            return response()->json([
                'errors' => [
                    ['code' => 'guest_id', 'message' => translate('messages.guest_id_required')],
                ],
            ], 403);
        }

        $latitude = (float) $request['latitude'];
        $longitude = (float) $request['longitude'];

        $zone_request = ZoneRequest::updateOrCreate(
            ['user_id' => $requester_id, 'is_guest' => $is_guest],
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address' => $request['address'],
                'nearest_zone_id' => $this->nearest_zone_id($latitude, $longitude),
                'source' => $request['source'],
                'store_id' => $request['store_id'],
                'module_id' => $request['module_id'],
                'fcm_token' => $request['fcm_token'],
                'has_push' => (bool) ($request['has_push'] ?? false),
                'ip_address' => $request->ip(),
            ]
        );

        return response()->json([
            'message' => translate('messages.zone_request_recorded'),
            'total_requests_in_area' => $this->count_near($latitude, $longitude),
        ], 200);
    }

    /**
     * Whether this requester is already on the list, plus the local count.
     * Lets the UI show "You're on the list" instead of prompting again.
     */
    public function status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        // Optional-auth pattern used across the guest-capable endpoints: the
        // middleware populates $request->user when a token is present.
        $user = $request->user instanceof \App\Models\User ? $request->user : null;
        $requester_id = $user ? $user->id : $request['guest_id'];
        $is_guest = $user ? 0 : 1;

        $requested = $requester_id
            ? ZoneRequest::where(['user_id' => $requester_id, 'is_guest' => $is_guest])->exists()
            : false;

        return response()->json([
            'requested' => $requested,
            'total_requests_in_area' => $this->count_near((float) $request['lat'], (float) $request['lng']),
        ], 200);
    }

    /**
     * How many other people asked for delivery near this point.
     *
     * Powers "you're the 47th person here" — the line that makes the tap feel
     * worth making. Bounding box only (see ZoneRequest::scopeNear); returns
     * null on failure so the UI hides the line rather than showing a zero that
     * would read as "nobody else wants this".
     */
    private function count_near(float $lat, float $lng): ?int
    {
        try {
            return ZoneRequest::near($lat, $lng)->count();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Nearest ACTIVE zone to a point, computed server-side.
     *
     * More trustworthy than the client's guess, which measures to polygon
     * vertices only. ST_Distance on the polygon itself accounts for the whole
     * boundary, so a point near the middle of a long edge resolves correctly.
     *
     * Returns null when spatial support or the zone table isn't usable — the
     * request is still worth recording without it, so this must never throw.
     */
    private function nearest_zone_id(float $lat, float $lng): ?int
    {
        try {
            $point = new Point($lat, $lng, POINT_SRID);

            // A containing zone (if any) is by definition the nearest.
            $containing = Zone::where('status', 1)
                ->whereContains('coordinates', $point)
                ->value('id');
            if ($containing) {
                return (int) $containing;
            }

            return Zone::where('status', 1)
                ->orderByRaw('ST_Distance(coordinates, ST_GeomFromText(?, ?))', [
                    "POINT($lng $lat)",
                    POINT_SRID,
                ])
                ->value('id');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
