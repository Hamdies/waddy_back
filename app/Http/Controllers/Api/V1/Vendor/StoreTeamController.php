<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\CentralLogics\AkedlyGateway;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\EmployeeRole;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use App\Services\StoreTeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * ST-1: Team & roles. The owner manages everyone; an employee whose role has
 * "Settings & team" manages the other employees, and can only hand out what
 * their own role has, so nobody can raise their own access.
 */
class StoreTeamController extends Controller
{
    public function index(Request $request)
    {
        if ($denied = $this->deny($request, 'employee')) {
            return $denied;
        }
        return response()->json($this->overview($request), 200);
    }

    /** Adds a member; they sign in with this number (phone OTP). */
    public function invite(Request $request)
    {
        if ($denied = $this->deny($request, 'employee')) {
            return $denied;
        }
        $store = $request['vendor']->stores[0];
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:100',
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|max:20',
            'role_id' => ['required', Rule::exists('employee_roles', 'id')->where('store_id', $store->id)],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $role = EmployeeRole::withoutGlobalScope('translate')->find($request->role_id);
        if ($denied = $this->exceeds($request, $role)) {
            return $denied;
        }

        $phone = AkedlyGateway::normalize_phone($request->phone);
        $candidates = $this->phoneCandidates($phone);
        if (VendorEmployee::whereIn('phone', $candidates)->exists() || Vendor::whereIn('phone', $candidates)->exists()) {
            return $this->error('phone', translate('messages.team_phone_taken'), 409);
        }

        [$first, $last] = array_pad(explode(' ', trim(preg_replace('/\s+/', ' ', $request->name)), 2), 2, null);
        $e = new VendorEmployee();
        $e->f_name = $first;
        $e->l_name = $last;
        $e->phone = $phone;
        // Staff sign in by phone; the panel still needs a unique email and a password.
        $e->email = 'staff-' . ltrim($phone, '+') . '@staff.waddyapp.com';
        $e->password = bcrypt(Str::random(32));
        $e->employee_role_id = $role->id;
        $e->vendor_id = $request['vendor']->id;
        $e->store_id = $store->id;
        $e->auth_token = null;
        $e->save();

        return response()->json($this->overview($request), 200);
    }

    /** Role and/or app access (status 0 = suspended, signed out everywhere). */
    public function update_member(Request $request, $id)
    {
        if ($denied = $this->deny($request, 'employee')) {
            return $denied;
        }
        $e = $this->member($request, $id);
        if (!$e) {
            return $this->error('member', translate('messages.not_found'), 404);
        }
        if ($request->has('role_id')) {
            $role = EmployeeRole::withoutGlobalScope('translate')->where('store_id', $e->store_id)->find($request->role_id);
            if (!$role) {
                return $this->error('role', translate('messages.not_found'), 404);
            }
            if ($denied = $this->exceeds($request, $role)) {
                return $denied;
            }
            $e->employee_role_id = $role->id;
        }
        if ($request->has('status')) {
            $e->status = filter_var($request->status, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        $e->save();
        if (isset($e->status) && !$e->status) {
            StoreTeamService::signOut($e);
        }
        return response()->json($this->overview($request), 200);
    }

    public function remove_member(Request $request, $id)
    {
        if ($denied = $this->deny($request, 'employee')) {
            return $denied;
        }
        $e = $this->member($request, $id);
        if (!$e) {
            return $this->error('member', translate('messages.not_found'), 404);
        }
        StoreTeamService::signOut($e);
        $e->delete();
        return response()->json($this->overview($request), 200);
    }

    public function store_role(Request $request)
    {
        return $this->saveRole($request, null);
    }

    public function update_role(Request $request, $id)
    {
        return $this->saveRole($request, $id);
    }

    /** Only a custom role with nobody in it. */
    public function delete_role(Request $request, $id)
    {
        if ($denied = $this->deny($request, 'custom_role')) {
            return $denied;
        }
        $store = $request['vendor']->stores[0];
        $role = EmployeeRole::withoutGlobalScope('translate')->where('store_id', $store->id)->find($id);
        if (!$role) {
            return $this->error('role', translate('messages.not_found'), 404);
        }
        if ($role->system_key || VendorEmployee::where('employee_role_id', $role->id)->exists()) {
            return $this->error('role', translate('messages.team_role_in_use'), 409);
        }
        $role->translations()->delete();
        $role->delete();
        return response()->json($this->overview($request), 200);
    }

    private function saveRole(Request $request, $id)
    {
        if ($denied = $this->deny($request, 'custom_role')) {
            return $denied;
        }
        $store = $request['vendor']->stores[0];
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:60',
                Rule::unique('employee_roles')->where('store_id', $store->id)->ignore($id)],
            'perms' => 'present|array',
            'perms.*' => 'in:' . implode(',', array_keys(StoreTeamService::PERMS)),
        ], [
            'name.unique' => translate('messages.Role name already taken!'),
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $role = $id ? EmployeeRole::withoutGlobalScope('translate')->where('store_id', $store->id)->find($id) : new EmployeeRole();
        if (!$role) {
            return $this->error('role', translate('messages.not_found'), 404);
        }
        $me = $request['vendor_employee'];
        if ($me && $id && $me->employee_role_id == $role->id) {
            return $this->error('role', translate('messages.team_own_role'), 403);
        }

        $keep = StoreTeamService::unmapped(StoreTeamService::modulesOf($id ? $role : null));
        $modules = StoreTeamService::toModules($request->perms ?? [], $keep);
        if ($me && array_diff($modules, StoreTeamService::modulesOf($me->role))) {
            return $this->error('role', translate('messages.team_more_than_you'), 403);
        }

        $role->name = trim($request->name);
        $role->modules = json_encode($modules);
        $role->status = 1;
        $role->store_id = $store->id;
        $role->save();
        // The panel may have saved per-language names; the app edits one.
        $role->translations()->delete();

        return response()->json($this->overview($request), 200);
    }

    private function overview(Request $request): array
    {
        return StoreTeamService::overview($request['vendor']->stores[0], $request['vendor'], $request['vendor_employee']);
    }

    /** Another employee of this store (never yourself). */
    private function member(Request $request, $id): ?VendorEmployee
    {
        $me = $request['vendor_employee'];
        if ($me && $me->id == $id) {
            return null;
        }
        return VendorEmployee::where('store_id', $request['vendor']->stores[0]->id)->find($id);
    }

    private function deny(Request $request, string $module)
    {
        $me = $request['vendor_employee'];
        if ($me && !in_array($module, StoreTeamService::modulesOf($me->role))) {
            return $this->error('permission', translate('messages.permission_denied'), 403);
        }
        return null;
    }

    /** An employee can only give a role with nothing more than their own. */
    private function exceeds(Request $request, EmployeeRole $role)
    {
        $me = $request['vendor_employee'];
        if ($me && array_diff(StoreTeamService::modulesOf($role), StoreTeamService::modulesOf($me->role))) {
            return $this->error('role', translate('messages.team_more_than_you'), 403);
        }
        return null;
    }

    private function phoneCandidates(string $phone): array
    {
        $digits = ltrim($phone, '+');
        $candidates = [$phone, $digits];
        if (str_starts_with($digits, '20')) {
            $candidates[] = '0' . substr($digits, 2);
        }
        return $candidates;
    }

    private function error(string $code, string $message, int $status)
    {
        return response()->json(['errors' => [['code' => $code, 'message' => $message]]], $status);
    }
}
