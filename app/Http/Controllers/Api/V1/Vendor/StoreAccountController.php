<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Services\StoreDocumentService;
use App\Services\StorePushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** MA-3, MA-4: the store app's Account hub (push switches, documents, payout account). */
class StoreAccountController extends Controller
{
    public function push_prefs(Request $request)
    {
        return response()->json(StorePushService::prefs($request['vendor']->stores[0]), 200);
    }

    /** Partial: only the keys sent change. */
    public function update_push_prefs(Request $request)
    {
        $store = $request['vendor']->stores[0];
        $prefs = StorePushService::prefs($store);
        foreach (array_keys(StorePushService::PUSH_DEFAULTS) as $key) {
            if ($request->has($key)) {
                $prefs[$key] = filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN);
            }
        }
        $store->push_prefs = $prefs;
        $store->save();
        return response()->json($prefs, 200);
    }

    public function documents(Request $request)
    {
        $store = $request['vendor']->stores[0];
        return response()->json(['documents' => StoreDocumentService::overview($store)], 200);
    }

    public function upload_document(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:' . implode(',', StoreDocumentService::FILE_TYPES),
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,heic,pdf|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $store = $request['vendor']->stores[0];
        StoreDocumentService::upload($store, $request['type'], $request->file('file'));
        return response()->json(['documents' => StoreDocumentService::overview($store)], 200);
    }

    public function update_bank(Request $request)
    {
        if ($request['vendor_employee']) {
            return response()->json(['errors' => [['code' => 'bank', 'message' => translate('messages.permission_denied')]]], 403);
        }
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:191',
            'holder_name' => 'required|string|max:191',
            'account_no' => 'required|string|min:6|max:64',
            'branch' => 'nullable|string|max:191',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $store = $request['vendor']->stores[0];
        $applied = StoreDocumentService::requestBank($store, $request['vendor'], $validator->validated());
        return response()->json([
            'applied' => $applied,
            'documents' => StoreDocumentService::overview($store->fresh()),
        ], 200);
    }
}
