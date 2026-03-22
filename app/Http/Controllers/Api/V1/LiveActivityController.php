<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LiveActivityToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveActivityController extends Controller
{
    /**
     * Store or update an iOS Live Activity push token for an order.
     *
     * POST /api/v1/customer/live-activity-token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'   => 'required|integer|exists:orders,id',
            'push_token' => 'required|string|max:512',
        ]);

        $userId = $request->user()?->id;

        LiveActivityToken::updateOrCreate(
            ['order_id' => $request->order_id],
            [
                'user_id'    => $userId,
                'push_token' => $request->push_token,
                'platform'   => 'ios',
            ]
        );

        return response()->json([
            'message' => 'Live Activity token stored successfully',
        ], 200);
    }
}
