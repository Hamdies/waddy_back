<?php

namespace App\Services\AdminPanel\Resources;

use App\Services\AdminPanel\Resource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Admin accounts and their roles. New admins need a password and photo, so they are added in the classic admin. */
class AdminsResource extends Resource
{
    public ?string $permission = 'employee';
    protected string $idColumn = 'a.id';
    protected ?string $zoneColumn = 'a.zone_id';
    protected array $search = ['a.f_name', 'a.l_name', 'a.email', 'r.name'];
    protected array $sorts = ['name' => 'a.f_name', 'role' => 'r.name', 'zone' => 'z.name', 'lastActive' => 'a.updated_at'];

    public function config(): array
    {
        return [
            'label' => self::L('Admin users', 'المشرفين'), 'one' => self::L('Admin', 'مشرف'),
            'titleKey' => 'name', 'subKey' => 'email', 'statusKey' => 'status',
            'zoneKey' => true, 'editable' => true, 'newUrl' => route('admin.users.employee.add-new'), 'search' => ['name', 'email', 'role'],
            'filter' => ['key' => 'status', 'options' => ['active', 'disabled']],
            'columns' => [
                ['key' => 'name', 'label' => self::L('Name', 'الاسم'), 'sub' => 'email', 'w' => '1.8fr', 'strong' => true, 'sort' => 'name'],
                ['key' => 'role', 'label' => self::L('Role', 'الدور'), 'w' => '1.1fr', 'sort' => 'role'],
                ['key' => 'zone', 'label' => self::L('Zone access', 'المنطقة'), 'w' => '0.9fr', 'sort' => 'zone'],
                ['key' => 'signedIn', 'label' => self::L('Signed in', 'متصل'), 'fmt' => 'bool', 'w' => '0.6fr'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.8fr'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => self::L('Full name', 'الاسم'), 'required' => true],
                ['key' => 'email', 'label' => self::L('Email', 'البريد الإلكتروني'), 'type' => 'email', 'required' => true],
                ['key' => 'phone', 'label' => self::L('Phone', 'الهاتف')],
                ['key' => 'role_id', 'label' => self::L('Role', 'الدور'), 'type' => 'select', 'show' => 'role'],
                ['key' => 'zone', 'label' => self::L('Zone access', 'المنطقة'), 'readonly' => true],
                ['key' => 'signedIn', 'label' => self::L('Signed in now', 'متصل الآن'), 'fmt' => 'bool', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
            'lines' => ['title' => self::L('Permissions', 'الصلاحيات')],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['disabled' => 'r.status = 0'], 'active');
    }

    protected function query(string $mod): Builder
    {
        return DB::table('admins as a')
            ->leftJoin('admin_roles as r', 'r.id', '=', 'a.role_id')
            ->leftJoin('zones as z', 'z.id', '=', 'a.zone_id')
            ->select('a.id', 'a.f_name', 'a.l_name', 'a.email', 'a.phone', 'a.role_id', 'a.zone_id', 'a.is_logged_in',
                'r.name as role_name', 'r.modules', 'z.name as zone_name')
            ->selectRaw('(' . $this->statusSql() . ') as st');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'name' => self::name($r->f_name, $r->l_name),
            'email' => $r->email,
            'phone' => $r->phone,
            'role' => $r->role_id == 1 ? 'Owner' : ($r->role_name ?: '—'),
            'role_id' => $r->role_id,
            'zone' => $r->zone_name ?: 'All zones',
            'signedIn' => (bool) $r->is_logged_in,
            'modules' => $r->role_id == 1 ? null : (json_decode($r->modules ?? '', true) ?: []),
            'status' => $r->st,
        ];
    }

    protected function lines(array $row): array
    {
        if ($row['modules'] === null) {
            return [['All sections', 'Full access', '']];
        }
        return collect($row['modules'])->map(fn ($m) => [ucwords(str_replace('_', ' ', $m)), 'Full access', ''])->all();
    }

    public function options(?array $row = null): array
    {
        $roles = DB::table('admin_roles')->where('id', '!=', 1)->orderBy('name')->get(['id', 'name']);
        if ($row && $row['role_id'] == 1) {
            return ['role_id' => [['value' => 1, 'label' => 'Owner']]];
        }
        return ['role_id' => $roles->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])->all()];
    }

    protected function classicUrl(array $row): ?string
    {
        return $row['role_id'] == 1 ? null : route('admin.users.employee.edit', ['id' => $row['id']]);
    }

    public function update(int|string $id, array $data): array
    {
        $current = $this->find($id);
        $v = Validator::make($data, [
            'name' => 'required|string|max:200',
            'email' => ['required', 'email', 'max:100', Rule::unique('admins', 'email')->ignore($id)],
            'phone' => 'nullable|string|max:20',
            'role_id' => ['required', Rule::exists('admin_roles', 'id')],
        ]);
        if ($v->fails() || !$current) {
            return self::fail($v->errors()->first() ?: translate('messages.not_found'));
        }
        // The owner keeps the owner role, and nobody is promoted to it here.
        $role = $current['role_id'] == 1 ? 1 : ((int) $data['role_id'] === 1 ? $current['role_id'] : (int) $data['role_id']);
        [$first, $last] = array_pad(explode(' ', trim($data['name']), 2), 2, '');
        DB::table('admins')->where('id', $id)->update([
            'f_name' => $first, 'l_name' => $last, 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'role_id' => $role, 'updated_at' => now(),
        ]);
        return self::ok(translate('messages.employee_updated_successfully'));
    }
}
