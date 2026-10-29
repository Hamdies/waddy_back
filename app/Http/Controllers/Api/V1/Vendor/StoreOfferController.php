<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\StoreOffer;
use App\Services\StoreOfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** OF-1: the store app's Offers screen. */
class StoreOfferController extends Controller
{
    /** Offers, newest first, each with its results; `live` says if it applies right now. */
    public function index(Request $request)
    {
        $store = $request['vendor']->stores[0];
        $offers = StoreOffer::where('store_id', $store->id)->latest('id')->limit(30)->get()
            ->map(fn (StoreOffer $o) => array_merge($o->only(['id', 'type', 'value', 'min_order', 'max_discount', 'start_date', 'end_date', 'budget', 'status', 'ended_reason', 'created_at']), [
                'start_time' => substr($o->start_time, 0, 5),
                'end_time' => substr($o->end_time, 0, 5),
                'live' => StoreOfferService::isLive($o),
                'results' => StoreOfferService::results($o),
            ]));
        return response()->json([
            'offers' => $offers,
            'commission' => (float) ($store->comission ?? Helpers::get_business_settings('admin_commission') ?? 0),
            'waddy_discount' => (bool) StoreOfferService::adminDiscount($store),
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:' . implode(',', StoreOffer::TYPES),
            'value' => 'required_unless:type,free_delivery|nullable|numeric|min:1',
            'min_order' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'budget' => 'nullable|numeric|min:1',
        ]);
        $validator->after(function ($v) use ($request) {
            if ($request['type'] === 'percent' && (float) $request['value'] > 90) {
                $v->errors()->add('value', translate('messages.offer_percent_max'));
            }
            if ($request['type'] === 'amount' && (float) ($request['min_order'] ?? 0) < (float) $request['value']) {
                // 50 off a 30 basket would be free food: the minimum order covers the amount.
                $v->errors()->add('min_order', translate('messages.offer_min_order_covers_amount'));
            }
        });
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $data = $validator->validated();
        foreach (['start_time', 'end_time'] as $t) {
            if (!empty($data[$t])) {
                $data[$t] .= $t === 'end_time' ? ':59' : ':00';
            }
        }
        $offer = StoreOfferService::create($request['vendor']->stores[0], $data);
        if (is_string($offer)) {
            return response()->json(['errors' => [['code' => 'offer', 'message' => translate($offer)]]], 403);
        }
        return response()->json(['id' => $offer->id, 'message' => translate('messages.offer_started')], 200);
    }

    public function status(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['status' => 'required|in:active,paused,ended']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $offer = StoreOffer::where('store_id', $request['vendor']->stores[0]->id)->find($id);
        if (!$offer || $offer->status === 'ended') {
            return response()->json(['errors' => [['code' => 'offer', 'message' => translate('messages.not_found')]]], 404);
        }
        StoreOfferService::setStatus($offer, $request['status']);
        return response()->json(['message' => translate('messages.offer_updated')], 200);
    }
}
