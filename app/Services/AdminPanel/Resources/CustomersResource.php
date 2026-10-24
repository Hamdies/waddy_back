<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerWalletController;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Images;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Customers. Picking a module shows the customers who ordered from it;
 * "top module" is where most of their orders went.
 */
class CustomersResource extends Resource
{
    public ?string $permission = 'customer_management';
    protected string $idColumn = 'u.id';
    protected ?string $zoneColumn = 'u.zone_id';
    protected ?string $dateColumn = 'u.created_at';
    protected array $search = ['u.f_name', 'u.l_name', 'u.phone', 'u.email', "concat(u.f_name, ' ', u.l_name)"];
    protected array $sorts = ['name' => 'u.f_name', 'zone' => 'z.name', 'orders' => 'orders', 'spent' => 'spent', 'wallet' => 'u.wallet_balance', 'joined' => 'u.created_at'];

    public function config(): array
    {
        return [
            'label' => self::L('Customers', 'العملاء'), 'one' => self::L('Customer', 'عميل'),
            'titleKey' => 'name', 'subKey' => 'phone', 'statusKey' => 'status',
            'modKey' => 'favorite', 'dateKey' => true, 'zoneKey' => true,
            'editable' => true, 'search' => ['name', 'phone', 'email'],
            'filter' => ['key' => 'status', 'options' => ['active', 'blocked']],
            'columns' => [
                ['key' => 'name', 'label' => self::L('Customer', 'العميل'), 'sub' => 'email', 'w' => '1.8fr', 'strong' => true, 'sort' => 'name'],
                ['key' => 'favorite', 'label' => self::L('Top module', 'القسم المفضل'), 'fmt' => 'module', 'w' => '0.9fr', 'allOnly' => true],
                ['key' => 'zone', 'label' => self::L('Zone', 'المنطقة'), 'w' => '1fr', 'sort' => 'zone'],
                ['key' => 'orders', 'label' => self::L('Orders', 'الطلبات'), 'fmt' => 'int', 'w' => '0.6fr', 'sort' => 'orders'],
                ['key' => 'spent', 'label' => self::L('Lifetime spend', 'إجمالي الإنفاق'), 'fmt' => 'egp', 'w' => '1fr', 'sort' => 'spent'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.8fr'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => self::L('Full name', 'الاسم'), 'required' => true],
                ['key' => 'phone', 'label' => self::L('Phone', 'الهاتف'), 'tel' => true, 'required' => true],
                ['key' => 'email', 'label' => self::L('Email', 'البريد الإلكتروني'), 'type' => 'email'],
                ['key' => 'zone_id', 'label' => self::L('Zone', 'المنطقة'), 'type' => 'select', 'show' => 'zone'],
                ['key' => 'favorite', 'label' => self::L('Top module', 'القسم المفضل'), 'readonly' => true, 'mod' => true],
                ['key' => 'orders', 'label' => self::L('Orders', 'الطلبات'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'spent', 'label' => self::L('Lifetime spend', 'إجمالي الإنفاق'), 'fmt' => 'egp', 'readonly' => true],
                ['key' => 'wallet', 'label' => self::L('Wallet balance', 'رصيد المحفظة'), 'fmt' => 'egp', 'readonly' => true],
                ['key' => 'xp', 'label' => self::L('XP', 'النقاط'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'joined', 'label' => self::L('Joined', 'تاريخ الانضمام'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['blocked' => 'u.status = 0'], 'active');
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('users as u')
            ->leftJoin('zones as z', 'z.id', '=', 'u.zone_id')
            ->select('u.id', 'u.f_name', 'u.l_name', 'u.image', 'u.phone', 'u.email', 'u.zone_id', 'u.wallet_balance', 'u.total_xp', 'u.created_at', 'z.name as zone_name')
            ->selectRaw('(select count(*) from orders where orders.user_id = u.id) as orders')
            ->selectRaw("(select coalesce(sum(order_amount), 0) from orders where orders.user_id = u.id and order_status = 'delivered') as spent")
            ->selectRaw('(select module_id from orders where orders.user_id = u.id group by module_id order by count(*) desc limit 1) as top_module')
            ->selectRaw('(' . $this->statusSql() . ') as st');
        Images::selectDisk($q, User::class, 'u.id', 'image', 'image_disk');
        if ($zone = Scope::adminZone()) {
            $q->where('u.zone_id', $zone);
        }
        $ids = Scope::ids($mod);
        if ($ids !== null) {
            $q->whereExists(fn ($e) => $e->selectRaw(1)->from('orders')->whereColumn('orders.user_id', 'u.id')->whereIn('orders.module_id', $ids ?: [0]));
        }
        return $q;
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'name' => self::name($r->f_name, $r->l_name) ?: '—',
            'img' => $img = Images::url('profile', $r->image, $r->image_disk),
            'avatars' => ['name' => $img],
            'phone' => $r->phone,
            'email' => $r->email,
            'zone' => $r->zone_name,
            'zone_id' => $r->zone_id,
            'favorite' => $r->top_module ? Scope::code($r->top_module) : null,
            'orders' => (int) $r->orders,
            'spent' => (float) $r->spent,
            'wallet' => (float) $r->wallet_balance,
            'xp' => (int) $r->total_xp,
            'joined' => self::date($r->created_at),
            'status' => $r->st,
        ];
    }

    public function options(?array $row = null): array
    {
        return ['zone_id' => DB::table('zones')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($z) => ['value' => $z->id, 'label' => $z->name])->prepend(['value' => '', 'label' => '—'])->all()];
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.customer.view', ['user_id' => $row['id']]);
    }

    protected function actions(): array
    {
        $status = fn ($id, $s) => ClassicAction::run(CustomerController::class, 'status', ['status' => $s], ['customer' => User::find($id)], 'GET');
        return [
            ['key' => 'credit', 'label' => self::L('Add EGP 50 credit', 'إضافة ٥٠ ج.م رصيد'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'active',
                'run' => function ($x) {
                    if (BusinessSetting::where('key', 'wallet_status')->value('value') != 1) {
                        return self::fail(translate('messages.customer_wallet_disable_warning_admin'));
                    }
                    $result = ClassicAction::run(CustomerWalletController::class, 'add_fund', ['customer_id' => $x['id'], 'amount' => 50, 'referance' => 'Admin panel']);
                    return $result['ok'] ? self::ok(translate('messages.fund_added_successfully')) : $result;
                }],
            ['key' => 'block', 'label' => self::L('Block customer', 'حظر العميل'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'active', 'run' => fn ($x) => $status($x['id'], 0)],
            ['key' => 'unblock', 'label' => self::L('Unblock', 'إلغاء الحظر'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'blocked', 'run' => fn ($x) => $status($x['id'], 1)],
        ];
    }

    public function update(int|string $id, array $data): array
    {
        $v = Validator::make($data, [
            'name' => 'required|string|max:200',
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($id)],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users', 'email')->ignore($id)],
            'zone_id' => 'nullable|exists:zones,id',
        ]);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        [$first, $last] = array_pad(explode(' ', trim($data['name']), 2), 2, '');
        DB::table('users')->where('id', $id)->update([
            'f_name' => $first,
            'l_name' => $last,
            'phone' => $data['phone'],
            'email' => $data['email'] ?: null,
            'zone_id' => $data['zone_id'] ?: null,
            'updated_at' => now(),
        ]);
        return self::ok(translate('messages.updated_successfully'));
    }
}
