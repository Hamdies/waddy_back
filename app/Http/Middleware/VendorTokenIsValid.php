<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use App\Services\StoreTeamService;

class VendorTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token=$request->bearerToken();
        if(strlen($token)<1)
        {
            return response()->json([
                'errors' => [
                    ['code' => 'auth-001', 'message' => 'Unauthorized.']
                ]
            ], 401);
        }
        if (!$request->hasHeader('vendorType')) {
            $errors = [];
            array_push($errors, ['code' => 'vendor_type', 'message' => translate('messages.vendor_type_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $vendor_type= $request->header('vendorType');
        if($vendor_type == 'owner'){
            $vendor = Vendor::where('auth_token', $token)->first();
            if(!isset($vendor))
            {
                return response()->json([
                    'errors' => [
                        ['code' => 'auth-001', 'message' => 'Unauthorized.']
                    ]
                ], 401);
            }
            $request['vendor']=$vendor;
            Config::set('module.current_module_data', $vendor->stores[0]->module);
            StoreTeamService::touch($vendor);
        }elseif($vendor_type == 'employee'){
            $vendor = VendorEmployee::where('auth_token', $token)->first();
            if(!isset($vendor))
            {
                return response()->json([
                    'errors' => [
                        ['code' => 'auth-001', 'message' => 'Unauthorized.']
                    ]
                ], 401);
            }
            // ST-1: suspended from the owner's Team screen.
            if (isset($vendor->status) && !$vendor->status) {
                return response()->json([
                    'errors' => [
                        ['code' => 'auth-001', 'message' => translate('messages.account_suspended')]
                    ]
                ], 401);
            }
            StoreTeamService::touch($vendor);
            $request['vendor']=$vendor->vendor;
            $request['vendor_employee']=$vendor;
            Config::set('module.current_module_data', $vendor->vendor->stores[0]->module);
        }
        return $next($request);
    }
}
