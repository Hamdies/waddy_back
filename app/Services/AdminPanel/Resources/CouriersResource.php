<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\DeliveryMan\DeliveryManController;
use App\Models\DeliveryMan;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Images;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Delivery men. Online = approved, not suspended, and active in the app. */
class CouriersResource extends Resource
{
    public ?string $permission = 'deliveryman';
    protected string $idColumn = 'd.id';
    protected ?string $zoneColumn = 'd.zone_id';
    protected ?string $dateColumn = 'd.created_at';
    protected array $search = ['d.f_name', 'd.l_name', 'd.phone', "concat(d.f_name, ' ', d.l_name)"];
    protected array $sorts = ['name' => 'd.f_name', 'zone' => 'z.name', 'deliveries' => 'd.order_count', 'current' => 'd.current_orders', 'rating' => 'rating'];

    public function config(): array
    {
        return [
            'label' => self::L('Couriers', 'المندوبين'), 'one' => self::L('Courier', 'مندوب'),
            'titleKey' => 'name', 'subKey' => 'phone', 'statusKey' => 'status',
            'dateKey' => true, 'zoneKey' => true,
            'editable' => true, 'newUrl' => route('admin.users.delivery-man.add'), 'search' => ['name', 'phone'],
            'filter' => ['key' => 'status', 'options' => ['online', 'offline', 'suspended', 'pending', 'rejected']],
            'columns' => [
                ['key' => 'name', 'label' => self::L('Courier', 'المندوب'), 'sub' => 'vehicle', 'w' => '1.6fr', 'strong' => true, 'sort' => 'name'],
                ['key' => 'zone', 'label' => self::L('Home zone', 'المنطقة'), 'w' => '1fr', 'sort' => 'zone'],
                ['key' => 'deliveries', 'label' => self::L('Deliveries', 'التوصيلات'), 'fmt' => 'int', 'w' => '0.8fr', 'sort' => 'deliveries'],
                ['key' => 'current', 'label' => self::L('Active orders', 'طلبات حالية'), 'fmt' => 'int', 'w' => '0.8fr', 'sort' => 'current'],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'rating', 'w' => '0.7fr', 'sort' => 'rating'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.9fr'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => self::L('Full name', 'الاسم'), 'required' => true],
                ['key' => 'phone', 'label' => self::L('Phone', 'الهاتف'), 'tel' => true, 'required' => true],
                ['key' => 'nationalId', 'label' => self::L('National ID', 'الرقم القومي')],
                ['key' => 'vehicle', 'label' => self::L('Vehicle', 'المركبة'), 'readonly' => true],
                ['key' => 'zone_id', 'label' => self::L('Home zone', 'المنطقة'), 'type' => 'select', 'show' => 'zone'],
                ['key' => 'deliveries', 'label' => self::L('Deliveries', 'التوصيلات'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'current', 'label' => self::L('Active orders', 'طلبات حالية'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'rating', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
                ['key' => 'joined', 'label' => self::L('Joined', 'تاريخ الانضمام'), 'fmt' => 'date', 'readonly' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases([
            'pending' => "d.application_status = 'pending'",
            'rejected' => "d.application_status = 'denied'",
            'suspended' => 'd.status = 0',
            'online' => 'd.active = 1',
        ], 'offline');
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('delivery_men as d')
            ->leftJoin('zones as z', 'z.id', '=', 'd.zone_id')
            ->leftJoin('d_m_vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->whereNull('d.store_id')
            ->select('d.id', 'd.f_name', 'd.l_name', 'd.image', 'd.phone', 'd.identity_number', 'd.zone_id', 'd.order_count', 'd.current_orders',
                'd.created_at', 'z.name as zone_name', 'v.type as vehicle')
            ->selectRaw('(select avg(rating) from d_m_reviews where d_m_reviews.delivery_man_id = d.id) as rating')
            ->selectRaw('(' . $this->statusSql() . ') as st');
        Images::selectDisk($q, DeliveryMan::class, 'd.id', 'image', 'image_disk');
        if ($zone = Scope::adminZone()) {
            $q->where('d.zone_id', $zone);
        }
        return $q;
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'name' => self::name($r->f_name, $r->l_name),
            'img' => $img = Images::url('delivery-man', $r->image, $r->image_disk),
            'avatars' => ['name' => $img],
            'phone' => $r->phone,
            'nationalId' => $r->identity_number,
            'vehicle' => $r->vehicle ?: '—',
            'zone' => $r->zone_name,
            'zone_id' => $r->zone_id,
            'deliveries' => (int) $r->order_count,
            'current' => (int) $r->current_orders,
            'rating' => $r->rating ? number_format($r->rating, 1) : '—',
            'status' => $r->st,
            'joined' => self::date($r->created_at),
        ];
    }

    public function options(?array $row = null): array
    {
        return ['zone_id' => DB::table('zones')->when(Scope::adminZone(), fn ($q, $z) => $q->where('id', $z))
            ->orderBy('name')->get(['id', 'name'])->map(fn ($z) => ['value' => $z->id, 'label' => $z->name])->all()];
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.users.delivery-man.preview', ['id' => $row['id']]);
    }

    protected function actions(): array
    {
        $run = fn ($method, $id, $s) => ClassicAction::run(DeliveryManController::class, $method, ['id' => $id, 'status' => $s], [], 'GET');
        return [
            ['key' => 'approve', 'label' => self::L('Approve', 'اعتماد'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'pending', 'run' => fn ($x) => $run('updateApplication', $x['id'], 'approved')],
            ['key' => 'deny', 'label' => self::L('Deny', 'رفض'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'pending', 'run' => fn ($x) => $run('updateApplication', $x['id'], 'denied')],
            ['key' => 'suspend', 'label' => self::L('Suspend courier', 'إيقاف المندوب'), 'tone' => 'danger',
                'when' => fn ($x) => in_array($x['status'], ['online', 'offline'], true), 'run' => fn ($x) => $run('updateStatus', $x['id'], 0)],
            ['key' => 'reinstate', 'label' => self::L('Reinstate', 'إعادة التفعيل'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'suspended', 'run' => fn ($x) => $run('updateStatus', $x['id'], 1)],
        ];
    }

    public function update(int|string $id, array $data): array
    {
        $v = Validator::make($data, [
            'name' => 'required|string|max:200',
            'phone' => ['required', 'string', 'max:20', Rule::unique('delivery_men', 'phone')->ignore($id)],
            'nationalId' => 'nullable|string|max:30',
            'zone_id' => 'nullable|exists:zones,id',
        ]);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        [$first, $last] = array_pad(explode(' ', trim($data['name']), 2), 2, '');
        DB::table('delivery_men')->where('id', $id)->update([
            'f_name' => $first,
            'l_name' => $last,
            'phone' => $data['phone'],
            'identity_number' => $data['nationalId'] ?? null,
            'zone_id' => $data['zone_id'] ?: DB::raw('zone_id'),
            'updated_at' => now(),
        ]);
        return self::ok(translate('messages.updated_successfully'));
    }
}
