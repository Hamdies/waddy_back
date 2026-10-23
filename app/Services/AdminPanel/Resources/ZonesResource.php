<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\Zone\ZoneController;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Delivery zones. Fees live per module in module_zone, so with one module
 * picked the fee columns are that module's; under "All" they show the
 * lowest across modules and are read-only.
 */
class ZonesResource extends Resource
{
    public ?string $permission = 'zone';
    protected string $idColumn = 'z.id';
    protected array $search = ['z.name', 'z.display_name'];
    protected array $sorts = ['name' => 'z.name', 'baseFee' => 'baseFee', 'perKm' => 'perKm', 'maxFee' => 'maxFee', 'orders30' => 'orders30', 'couriers' => 'couriers'];

    public function config(): array
    {
        return [
            'label' => self::L('Zones & fees', 'المناطق والرسوم'), 'one' => self::L('Zone', 'منطقة'),
            'titleKey' => 'name', 'subKey' => 'id', 'statusKey' => 'status',
            'editable' => true, 'newUrl' => route('admin.business-settings.zone.home'), 'search' => ['name'],
            'filter' => ['key' => 'status', 'options' => ['operating', 'busy', 'closed']],
            'columns' => [
                ['key' => 'name', 'label' => self::L('Zone', 'المنطقة'), 'w' => '1.4fr', 'strong' => true, 'sort' => 'name'],
                ['key' => 'baseFee', 'label' => self::L('Min. fee', 'أقل رسوم'), 'fmt' => 'egp', 'w' => '0.8fr', 'sort' => 'baseFee'],
                ['key' => 'perKm', 'label' => self::L('Per km', 'لكل كم'), 'fmt' => 'egp', 'w' => '0.7fr', 'sort' => 'perKm'],
                ['key' => 'maxFee', 'label' => self::L('Max. fee', 'أقصى رسوم'), 'fmt' => 'egp', 'w' => '0.8fr', 'sort' => 'maxFee'],
                ['key' => 'couriers', 'label' => self::L('Couriers', 'المندوبين'), 'fmt' => 'int', 'w' => '0.7fr', 'sort' => 'couriers'],
                ['key' => 'orders30', 'label' => self::L('Orders (30d)', 'طلبات ٣٠ يوم'), 'fmt' => 'int', 'w' => '0.8fr', 'sort' => 'orders30'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.9fr'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => self::L('Zone name', 'اسم المنطقة'), 'required' => true],
                ['key' => 'baseFee', 'label' => self::L('Minimum delivery fee (EGP)', 'أقل رسوم توصيل'), 'type' => 'number', 'fmt' => 'egp', 'perModule' => true],
                ['key' => 'perKm', 'label' => self::L('Fee per km (EGP)', 'رسوم كل كم'), 'type' => 'number', 'fmt' => 'egp', 'perModule' => true],
                ['key' => 'maxFee', 'label' => self::L('Maximum delivery fee (EGP)', 'أقصى رسوم توصيل'), 'type' => 'number', 'fmt' => 'egp', 'perModule' => true],
                ['key' => 'surge', 'label' => self::L('Surge pricing', 'تسعير الذروة'), 'type' => 'toggle', 'fmt' => 'bool'],
                ['key' => 'surgePct', 'label' => self::L('Surge (% added to fee)', 'نسبة الذروة'), 'type' => 'number', 'fmt' => 'pct'],
                ['key' => 'surgeNote', 'label' => self::L('Surge message in app', 'رسالة الذروة'), 'wide' => true],
                ['key' => 'cod', 'label' => self::L('Cash on delivery', 'الدفع عند الاستلام'), 'readonly' => true, 'fmt' => 'bool'],
                ['key' => 'couriers', 'label' => self::L('Couriers', 'المندوبين'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'orders30', 'label' => self::L('Orders (30d)', 'طلبات ٣٠ يوم'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['closed' => 'z.status = 0', 'busy' => 'z.increased_delivery_fee_status = 1'], 'operating');
    }

    protected function query(string $mod): Builder
    {
        $ids = Scope::ids($mod);
        $fees = DB::table('module_zone')
            ->when($ids !== null, fn ($q) => $q->whereIn('module_id', $ids ?: [0]))
            ->groupBy('zone_id')
            ->selectRaw('zone_id, min(minimum_shipping_charge) as base_fee, min(per_km_shipping_charge) as per_km, min(maximum_shipping_charge) as max_fee');
        $q = DB::table('zones as z')
            ->leftJoinSub($fees, 'f', 'f.zone_id', '=', 'z.id')
            ->select('z.id', 'z.name', 'z.display_name', 'z.status', 'z.cash_on_delivery', 'z.increased_delivery_fee', 'z.increased_delivery_fee_status',
                'z.increase_delivery_charge_message', 'f.base_fee as baseFee', 'f.per_km as perKm', 'f.max_fee as maxFee')
            ->selectRaw('(select count(*) from orders where orders.zone_id = z.id and orders.created_at >= ?) as orders30', [now()->subDays(30)])
            ->selectRaw('(select count(*) from delivery_men where delivery_men.zone_id = z.id and delivery_men.status = 1) as couriers')
            ->selectRaw('(' . $this->statusSql() . ') as st');
        if ($ids !== null) {
            $q->whereExists(fn ($e) => $e->selectRaw(1)->from('module_zone')->whereColumn('module_zone.zone_id', 'z.id')->whereIn('module_zone.module_id', $ids ?: [0]));
        }
        if ($zone = Scope::adminZone()) {
            $q->where('z.id', $zone);
        }
        return $q;
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->display_name ?: $r->name,
            'baseFee' => $r->baseFee === null ? null : (float) $r->baseFee,
            'perKm' => $r->perKm === null ? null : (float) $r->perKm,
            'maxFee' => $r->maxFee === null ? null : (float) $r->maxFee,
            'surge' => (bool) $r->increased_delivery_fee_status,
            'surgePct' => (float) $r->increased_delivery_fee,
            'surgeNote' => (string) $r->increase_delivery_charge_message,
            'cod' => (bool) $r->cash_on_delivery,
            'couriers' => (int) $r->couriers,
            'orders30' => (int) $r->orders30,
            'status' => $r->st,
        ];
    }

    public function find(int|string $id): ?array
    {
        $r = $this->query($this->mod)->where('z.id', $id)->first();
        return $r ? $this->row($r) : null;
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.business-settings.zone.edit', ['id' => $row['id']]);
    }

    protected function actions(): array
    {
        $status = fn ($id, $s) => ClassicAction::run(ZoneController::class, 'updateStatus', ['id' => $id, 'status' => $s], [], 'GET');
        return [
            ['key' => 'close', 'label' => self::L('Close zone', 'إغلاق المنطقة'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] !== 'closed', 'run' => fn ($x) => $status($x['id'], 0)],
            ['key' => 'reopen', 'label' => self::L('Reopen zone', 'إعادة فتح المنطقة'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'closed', 'run' => fn ($x) => $status($x['id'], 1)],
        ];
    }

    public function update(int|string $id, array $data): array
    {
        $v = Validator::make($data, [
            'name' => 'required|string|max:191',
            'baseFee' => 'nullable|numeric|min:0',
            'perKm' => 'nullable|numeric|min:0',
            'maxFee' => 'nullable|numeric|min:0',
            'surgePct' => 'nullable|numeric|min:0|max:500',
            'surgeNote' => 'nullable|string|max:255',
        ]);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        DB::table('zones')->where('id', $id)->update([
            'display_name' => $data['name'],
            'increased_delivery_fee_status' => !empty($data['surge']) ? 1 : 0,
            'increased_delivery_fee' => $data['surgePct'] ?? 0,
            'increase_delivery_charge_message' => $data['surgeNote'] ?? null,
            'updated_at' => now(),
        ]);
        $ids = Scope::ids($this->mod);
        if ($ids) {
            $fees = array_filter([
                'minimum_shipping_charge' => $data['baseFee'] ?? null,
                'per_km_shipping_charge' => $data['perKm'] ?? null,
                'maximum_shipping_charge' => $data['maxFee'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
            if ($fees) {
                DB::table('module_zone')->where('zone_id', $id)->whereIn('module_id', $ids)->update($fees);
            }
        }
        return self::ok(translate('messages.zone_updated_successfully'));
    }
}
