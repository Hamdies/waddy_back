<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\Coupon\CouponController;
use App\Models\Coupon;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Coupons. Zone- and store-wise coupons are created in the classic admin (they need id lists). */
class PromotionsResource extends Resource
{
    public ?string $permission = 'coupon';
    protected string $idColumn = 'c.id';
    protected ?string $dateColumn = 'c.start_date';
    protected array $search = ['c.code', 'c.title'];
    protected array $sorts = ['code' => 'c.code', 'module' => 'c.module_id', 'minOrder' => 'c.min_purchase', 'uses' => 'c.total_uses', 'ends' => 'c.expire_date'];

    private const TYPES = ['default' => 'Default', 'first_order' => 'First order', 'free_delivery' => 'Free delivery', 'zone_wise' => 'Zone wise', 'store_wise' => 'Store wise'];

    public function config(): array
    {
        return [
            'label' => self::L('Promotions', 'العروض'), 'one' => self::L('Promo code', 'كود خصم'),
            'titleKey' => 'code', 'subKey' => 'type', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'square' => true,
            'editable' => true, 'newable' => true, 'deletable' => true, 'search' => ['code', 'title'],
            'filter' => ['key' => 'status', 'options' => ['active', 'scheduled', 'paused', 'expired']],
            'columns' => [
                ['key' => 'code', 'label' => self::L('Code', 'الكود'), 'sub' => 'type', 'w' => '1.3fr', 'strong' => true, 'sort' => 'code'],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'fmt' => 'module', 'w' => '0.9fr', 'sort' => 'module'],
                ['key' => 'valueLabel', 'label' => self::L('Value', 'القيمة'), 'w' => '0.7fr'],
                ['key' => 'minOrder', 'label' => self::L('Min. order', 'الحد الأدنى'), 'fmt' => 'egp', 'w' => '0.9fr', 'sort' => 'minOrder'],
                ['key' => 'uses', 'label' => self::L('Uses', 'الاستخدام'), 'fmt' => 'int', 'w' => '0.6fr', 'sort' => 'uses'],
                ['key' => 'ends', 'label' => self::L('Ends', 'ينتهي'), 'fmt' => 'date', 'w' => '0.8fr', 'sort' => 'ends'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.9fr'],
            ],
            'fields' => [
                ['key' => 'code', 'label' => self::L('Code', 'الكود'), 'required' => true],
                ['key' => 'title', 'label' => self::L('Title shown in app', 'العنوان في التطبيق'), 'required' => true],
                ['key' => 'module_id', 'label' => self::L('Module', 'القسم'), 'type' => 'select', 'show' => 'moduleName'],
                ['key' => 'coupon_type', 'label' => self::L('Coupon type', 'نوع الكوبون'), 'type' => 'select', 'show' => 'type'],
                ['key' => 'discount_type', 'label' => self::L('Discount type', 'نوع الخصم'), 'type' => 'select', 'show' => 'discountLabel'],
                ['key' => 'value', 'label' => self::L('Value', 'القيمة'), 'type' => 'number'],
                ['key' => 'minOrder', 'label' => self::L('Minimum order (EGP)', 'الحد الأدنى (ج.م)'), 'type' => 'number', 'fmt' => 'egp'],
                ['key' => 'maxDiscount', 'label' => self::L('Max discount (EGP)', 'أقصى خصم (ج.م)'), 'type' => 'number', 'fmt' => 'egp'],
                ['key' => 'limit', 'label' => self::L('Uses per customer', 'مرات الاستخدام لكل عميل'), 'type' => 'number'],
                ['key' => 'uses', 'label' => self::L('Times used', 'مرات الاستخدام'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'starts', 'label' => self::L('Starts', 'يبدأ'), 'type' => 'date', 'fmt' => 'date', 'required' => true],
                ['key' => 'ends', 'label' => self::L('Ends', 'ينتهي'), 'type' => 'date', 'fmt' => 'date', 'required' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases([
            'expired' => 'c.expire_date < curdate()',
            'paused' => 'c.status = 0',
            'scheduled' => 'c.start_date > curdate()',
        ], 'active');
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('coupons as c')
            ->leftJoin('modules as m', 'm.id', '=', 'c.module_id')
            ->where(fn ($w) => $w->whereNull('c.created_by')->orWhere('c.created_by', 'admin'))
            ->select('c.*', 'm.module_name')
            ->selectRaw('(' . $this->statusSql() . ') as st');
        return self::module($q, $mod, 'c.module_id');
    }

    protected function row(object $r): array
    {
        $value = match (true) {
            $r->coupon_type === 'free_delivery' => 'Free delivery',
            $r->discount_type === 'percent' => (float) $r->discount . '%',
            default => 'EGP ' . (float) $r->discount,
        };
        return [
            'id' => $r->id,
            'code' => $r->code,
            'title' => $r->title,
            'module' => Scope::code($r->module_id),
            'module_id' => $r->module_id,
            'moduleName' => $r->module_name,
            'coupon_type' => $r->coupon_type,
            'type' => self::TYPES[$r->coupon_type] ?? $r->coupon_type,
            'discount_type' => $r->discount_type,
            'discountLabel' => $r->discount_type === 'percent' ? 'Percent' : 'Fixed amount',
            'value' => (float) $r->discount,
            'valueLabel' => $value,
            'minOrder' => (float) $r->min_purchase,
            'maxDiscount' => (float) $r->max_discount,
            'limit' => $r->limit,
            'uses' => (int) $r->total_uses,
            'starts' => self::date($r->start_date),
            'ends' => self::date($r->expire_date),
            'status' => $r->st,
        ];
    }

    public function options(?array $row = null): array
    {
        $types = collect(['default', 'first_order', 'free_delivery']);
        if ($row && !$types->contains($row['coupon_type'])) {
            $types->push($row['coupon_type']);
        }
        return [
            'module_id' => DB::table('modules')->whereNot('module_type', 'places')->orderBy('id')->get(['id', 'module_name'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => $m->module_name])->all(),
            'coupon_type' => $types->map(fn ($t) => ['value' => $t, 'label' => self::TYPES[$t] ?? $t])->all(),
            'discount_type' => [['value' => 'percent', 'label' => 'Percent'], ['value' => 'amount', 'label' => 'Fixed amount']],
        ];
    }

    public function blank(string $mod): ?array
    {
        $ids = Scope::ids($mod) ?: DB::table('modules')->whereNot('module_type', 'places')->orderBy('id')->pluck('id')->all();
        return [
            'code' => '', 'title' => '', 'module_id' => $ids[0] ?? null, 'module' => $mod === 'all' ? 'all' : $mod,
            'coupon_type' => 'default', 'discount_type' => 'percent', 'value' => 10, 'minOrder' => 100, 'maxDiscount' => 0,
            'limit' => 1, 'uses' => 0, 'starts' => now()->toDateString(), 'ends' => now()->addDays(30)->toDateString(), 'status' => 'scheduled',
        ];
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.coupon.edit', ['id' => $row['id']]);
    }

    protected function actions(): array
    {
        $status = fn ($id, $s) => ClassicAction::run(CouponController::class, 'updateStatus', ['id' => $id, 'status' => $s], [], 'GET');
        return [
            ['key' => 'end', 'label' => self::L('End now', 'إنهاء الآن'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] !== 'expired',
                'run' => function ($x) {
                    DB::table('coupons')->where('id', $x['id'])->update(['expire_date' => now()->subDay()->toDateString(), 'updated_at' => now()]);
                    return self::ok(translate('messages.coupon_status_updated'));
                }],
            ['key' => 'activate', 'label' => self::L('Activate now', 'تفعيل الآن'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'scheduled',
                'run' => function ($x) {
                    DB::table('coupons')->where('id', $x['id'])->update(['start_date' => now()->toDateString(), 'status' => 1, 'updated_at' => now()]);
                    return self::ok(translate('messages.coupon_status_updated'));
                }],
            ['key' => 'pause', 'label' => self::L('Pause', 'إيقاف'), 'tone' => 'neutral',
                'when' => fn ($x) => in_array($x['status'], ['active', 'scheduled'], true), 'run' => fn ($x) => $status($x['id'], 0)],
            ['key' => 'resume', 'label' => self::L('Resume', 'تشغيل'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'paused', 'run' => fn ($x) => $status($x['id'], 1)],
        ];
    }

    private function validator(array $data, ?int $id = null): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'code' => ['required', 'string', 'max:100', Rule::unique('coupons', 'code')->ignore($id)],
            'title' => 'required|string|max:191',
            'module_id' => 'required|exists:modules,id',
            'coupon_type' => 'required|in:default,first_order,free_delivery,zone_wise,store_wise',
            'discount_type' => 'required|in:percent,amount',
            'value' => 'required|numeric|min:0',
            'minOrder' => 'nullable|numeric|min:0',
            'maxDiscount' => 'nullable|numeric|min:0',
            'limit' => 'nullable|integer|min:0',
            'starts' => 'required|date',
            'ends' => 'required|date|after_or_equal:starts',
        ]);
    }

    public function create(array $data, string $mod): array
    {
        $v = $this->validator($data);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        if (in_array($data['coupon_type'], ['zone_wise', 'store_wise'], true)) {
            return self::fail('Zone- and store-wise coupons are set up in the classic admin.');
        }
        Config::set('module.current_module_id', (int) $data['module_id']);
        return ClassicAction::run(CouponController::class, 'add', [
            'code' => $data['code'], 'title' => [$data['title']], 'lang' => ['default'],
            'coupon_type' => $data['coupon_type'], 'discount_type' => $data['discount_type'], 'discount' => $data['value'],
            'min_purchase' => $data['minOrder'] ?? 0, 'max_discount' => $data['maxDiscount'] ?? 0, 'limit' => $data['limit'] ?? null,
            'start_date' => $data['starts'], 'expire_date' => $data['ends'],
        ]);
    }

    public function update(int|string $id, array $data): array
    {
        $v = $this->validator($data, (int) $id);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        DB::table('coupons')->where('id', $id)->update([
            'code' => $data['code'],
            'title' => $data['title'],
            'module_id' => $data['module_id'],
            'coupon_type' => $data['coupon_type'],
            'discount_type' => $data['discount_type'],
            'discount' => $data['value'],
            'min_purchase' => $data['minOrder'] ?? 0,
            'max_discount' => $data['maxDiscount'] ?? 0,
            'limit' => $data['coupon_type'] === 'first_order' ? 1 : ($data['limit'] ?? null),
            'start_date' => $data['starts'],
            'expire_date' => $data['ends'],
            'updated_at' => now(),
        ]);
        self::syncTranslation(Coupon::class, $id, 'title', $data['title']);
        return self::ok(translate('messages.coupon_updated_successfully'));
    }

    public function delete(int|string $id): array
    {
        return ClassicAction::run(CouponController::class, 'delete', ['id' => $id], [], 'DELETE');
    }
}
