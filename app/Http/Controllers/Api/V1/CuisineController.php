<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use App\Http\Controllers\Controller;
use App\Models\Cuisine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuisineController extends Controller
{
    /**
     * The cuisine filter list.
     */
    public function get_cuisines(): JsonResponse
    {
        $cuisines = Cuisine::active()
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'image', 'priority']);

        return response()->json($cuisines, 200);
    }

    /**
     * Stores carrying one cuisine, paginated like the store list so the two
     * are interchangeable on the client.
     */
    public function get_cuisine_stores(Request $request, $cuisine_id): JsonResponse
    {
        $cuisine = Cuisine::active()->find($cuisine_id);

        if (!$cuisine) {
            return response()->json([
                'errors' => [
                    ['code' => 'cuisine', 'message' => translate('messages.cuisine_not_found')],
                ],
            ], 404);
        }

        $zone_id = $request->header('zoneId');
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');

        $stores = StoreLogic::get_stores(
            zone_id: $zone_id,
            filter_data: 'all',
            type: $request->query('type', 'all'),
            store_type: 'all',
            limit: $request['limit'],
            offset: $request['offset'],
            featured: false,
            longitude: $longitude,
            latitude: $latitude,
            cuisine_id: $cuisine_id,
        );

        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);
        $stores['cuisine'] = [
            'id' => $cuisine->id,
            'name' => $cuisine->name,
        ];

        return response()->json($stores, 200);
    }
}
