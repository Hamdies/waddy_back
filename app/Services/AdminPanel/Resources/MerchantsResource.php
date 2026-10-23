<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\VendorController;
use App\Models\Store;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Stores. A store is pending until its vendor application is approved
 * (vendors.status null), and paused when the store itself is switched off.
 */
class MerchantsResource extends Resource
{
    public ?string $permission = 'store';
    protected string $idColumn = 's.id';
    protected ?string $zoneColumn = 's.zone_id';
    protected ?string $dateColumn = 's.created_at';
    protected array $search = ['s.name', 's.phone', 'v.f_name', 'v.l_name'];
    protected array $sorts = ['name' => 's.name', 'module' => 's.module_id', 'zone' => 'z.name', 'orders30' => 'orders30', 'commission' => 's.comission'];

    public function config(): array
    {
        return [
            'label' => self::L('Merchants', 'المتاجر'), 'one' => self::L('Merchant', 'متجر'),
            'titleKey' => 'name', 'subKey' => 'category', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'zoneKey' => true, 'square' => true,
            'editable' => true, 'newUrl' => route('admin.store.add'), 'search' => ['name', 'owner'],
            'filter' => ['key' => 'status', 'options' => ['active', 'paused', 'pending', 'rejected']],
            'columns' => [
                ['key' => 'name', 'label' => self::L('Name', 'الاسم'), 'sub' => 'category', 'w' => '1.7fr', 'strong' => true, 'sort' => 'name'],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'fmt' => 'module', 'w' => '0.8fr', 'allOnly' => true, 'sort' => 'module'],
                ['key' => 'zone', 'label' => self::L('Zone', 'المنطقة'), 'w' => '1fr', 'sort' => 'zone'],
                ['key' => 'orders30', 'label' => self::L('Orders (30d)', 'طلبات ٣٠ يوم'), 'fmt' => 'int', 'w' => '0.9fr', 'sort' => 'orders30'],
                ['key' => 'commission', 'label' => self::L('Commission', 'العمولة'), 'fmt' => 'pct', 'w' => '0.8fr', 'sort' => 'commission'],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'rating', 'w' => '0.7fr'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.9fr'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => self::L('Name', 'الاسم'), 'required' => true],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'readonly' => true, 'mod' => true],
                ['key' => 'zone_id', 'label' => self::L('Zone', 'المنطقة'), 'type' => 'select', 'show' => 'zone'],
                ['key' => 'owner', 'label' => self::L('Owner', 'المالك'), 'readonly' => true],
                ['key' => 'phone', 'label' => self::L('Phone', 'الهاتف')],
                ['key' => 'commission', 'label' => self::L('Commission % (empty = default)', 'نسبة العمولة'), 'type' => 'number', 'fmt' => 'pct'],
                ['key' => 'prep', 'label' => self::L('Delivery time', 'وقت التوصيل')],
                ['key' => 'minOrder', 'label' => self::L('Minimum order (EGP)', 'الحد الأدنى للطلب'), 'type' => 'number', 'fmt' => 'egp'],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'rating', 'readonly' => true],
                ['key' => 'orders30', 'label' => self::L('Orders (30d)', 'طلبات ٣٠ يوم'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
                ['key' => 'joined', 'label' => self::L('Joined', 'تاريخ الانضمام'), 'fmt' => 'date', 'readonly' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases([
            'pending' => 'v.status is null',
            'rejected' => 'v.status = 0',
            'active' => 's.status = 1',
        ], 'paused');
    }

    protected function query(string $mod): Builder
    {
        $since = now()->subDays(30);
        $q = DB::table('stores as s')
            ->join('vendors as v', 'v.id', '=', 's.vendor_id')
            ->leftJoin('zones as z', 'z.id', '=', 's.zone_id')
            ->leftJoin('modules as m', 'm.id', '=', 's.module_id')
            ->select('s.id', 's.name', 's.phone', 's.zone_id', 's.module_id', 's.comission', 's.delivery_time', 's.minimum_order',
                's.rating', 's.created_at', 'z.name as zone_name', 'm.module_name', 'v.f_name', 'v.l_name')
            ->selectRaw('(select count(*) from orders where orders.store_id = s.id and orders.created_at >= ?) as orders30', [$since])
            ->selectRaw('(' . $this->statusSql() . ') as st');
        if ($zone = Scope::adminZone()) {
            $q->where('s.zone_id', $zone);
        }
        return self::module($q, $mod, 's.module_id');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'module' => Scope::code($r->module_id),
            'category' => $r->module_name,
            'zone' => $r->zone_name,
            'zone_id' => $r->zone_id,
            'owner' => self::name($r->f_name, $r->l_name),
            'phone' => $r->phone,
            'commission' => $r->comission === null ? null : (float) $r->comission,
            'prep' => $r->delivery_time,
            'minOrder' => (float) $r->minimum_order,
            'rating' => self::rating($r->rating),
            'orders30' => (int) $r->orders30,
            'status' => $r->st,
            'joined' => self::date($r->created_at),
        ];
    }

    /** stores.rating holds review counts per star: {"1":n, …, "5":n}. */
    public static function rating(?string $json): string
    {
        $counts = json_decode($json ?? '', true) ?: [];
        $n = array_sum($counts);
        if (!$n) {
            return '—';
        }
        $sum = 0;
        foreach ($counts as $star => $c) {
            $sum += (int) $star * $c;
        }
        return number_format($sum / $n, 1);
    }

    public function options(?array $row = null): array
    {
        return ['zone_id' => DB::table('zones')->when(Scope::adminZone(), fn ($q, $z) => $q->where('id', $z))
            ->orderBy('name')->get(['id', 'name'])->map(fn ($z) => ['value' => $z->id, 'label' => $z->name])->all()];
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.store.view', ['store' => $row['id']]);
    }

    protected function actions(): array
    {
        return [
            ['key' => 'approve', 'label' => self::L('Approve', 'اعتماد'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'pending',
                'run' => fn ($x) => ClassicAction::run(VendorController::class, 'update_application', ['id' => $x['id'], 'status' => 1], [], 'GET')],
            ['key' => 'reject', 'label' => self::L('Reject', 'رفض'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'pending',
                'run' => fn ($x) => ClassicAction::run(VendorController::class, 'update_application', ['id' => $x['id'], 'status' => 0], [], 'GET')],
            ['key' => 'pause', 'label' => self::L('Pause', 'إيقاف'), 'tone' => 'neutral',
                'when' => fn ($x) => $x['status'] === 'active',
                'run' => fn ($x) => $this->toggle($x['id'], 0)],
            ['key' => 'resume', 'label' => self::L('Resume', 'تشغيل'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'paused',
                'run' => fn ($x) => $this->toggle($x['id'], 1)],
        ];
    }

    private function toggle(int $id, int $status): array
    {
        $store = Store::withoutGlobalScopes()->find($id);
        return ClassicAction::run(VendorController::class, 'status', ['status' => $status], ['store' => $store], 'GET');
    }

    public function update(int|string $id, array $data): array
    {
        $v = Validator::make($data, [
            'name' => 'required|string|max:191',
            'phone' => 'nullable|string|max:20',
            'zone_id' => 'nullable|exists:zones,id',
            'commission' => 'nullable|numeric|between:0,100',
            'minOrder' => 'nullable|numeric|min:0',
            'prep' => 'nullable|string|max:100',
        ]);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        DB::table('stores')->where('id', $id)->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'zone_id' => $data['zone_id'] ?: DB::raw('zone_id'),
            'comission' => ($data['commission'] ?? '') === '' ? null : $data['commission'],
            'minimum_order' => $data['minOrder'] ?? 0,
            'delivery_time' => $data['prep'] ?? null,
            'updated_at' => now(),
        ]);
        self::syncTranslation(Store::class, $id, 'name', $data['name']);
        return self::ok(translate('messages.store_updated_successfully'));
    }
}
