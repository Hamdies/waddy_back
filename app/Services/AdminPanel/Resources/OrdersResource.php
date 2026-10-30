<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\OrderController;
use App\Models\BusinessSetting;
use App\Models\Refund;
use App\Models\Store;
use App\Models\User;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Images;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Orders. The panel's five statuses fold the order lifecycle:
 * pending → handover is "preparing", picked_up is "on the way". The exact
 * stage stays visible in the side panel.
 */
class OrdersResource extends Resource
{
    public ?string $permission = 'order';
    protected string $idColumn = 'o.id';
    protected ?string $zoneColumn = 's.zone_id';
    protected ?string $merchantColumn = 'o.store_id';
    protected ?string $merchantNameSql = 's.name';
    protected ?string $dateColumn = 'o.created_at';
    protected array $search = ['s.name', 'u.f_name', 'u.l_name', 'u.phone'];
    protected array $sorts = ['id' => 'o.id', 'customer' => 'u.f_name', 'module' => 'o.module_id', 'zone' => 'z.name', 'total' => 'o.order_amount', 'date' => 'o.created_at'];

    public const ACTIVE = ['new', 'preparing', 'onway'];

    public function config(): array
    {
        return [
            'label' => self::L('Orders', 'الطلبات'), 'one' => self::L('Order', 'طلب'),
            'titleKey' => 'id', 'subKey' => 'merchant', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'zoneKey' => true, 'merchantKey' => true,
            'editable' => true, 'search' => ['id', 'customer', 'merchant'],
            'filter' => ['key' => 'status', 'options' => ['new', 'preparing', 'onway', 'delivered', 'refundreq', 'cancelled', 'refunded']],
            'columns' => [
                ['key' => 'id', 'label' => self::L('Order', 'الطلب'), 'w' => '0.8fr', 'strong' => true, 'sort' => 'id'],
                ['key' => 'customer', 'label' => self::L('Customer', 'العميل'), 'sub' => 'merchant', 'w' => '1.7fr', 'sort' => 'customer'],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'fmt' => 'module', 'w' => '0.8fr', 'allOnly' => true, 'sort' => 'module'],
                ['key' => 'zone', 'label' => self::L('Zone', 'المنطقة'), 'w' => '1fr', 'sort' => 'zone'],
                ['key' => 'total', 'label' => self::L('Total', 'الإجمالي'), 'fmt' => 'egp', 'w' => '0.9fr', 'sort' => 'total'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '1fr'],
                ['key' => 'date', 'label' => self::L('Date', 'التاريخ'), 'fmt' => 'date', 'w' => '0.8fr', 'sort' => 'date'],
            ],
            'fields' => [
                ['key' => 'customer', 'label' => self::L('Customer', 'العميل'), 'readonly' => true, 'link' => ['customers', 'customer_id']],
                ['key' => 'phone', 'label' => self::L('Phone', 'الهاتف'), 'readonly' => true, 'tel' => true],
                ['key' => 'merchant', 'label' => self::L('Merchant', 'المتجر'), 'readonly' => true, 'link' => ['merchants', 'merchant_id']],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'readonly' => true, 'mod' => true],
                ['key' => 'zone', 'label' => self::L('Zone', 'المنطقة'), 'readonly' => true],
                ['key' => 'courier_id', 'label' => self::L('Courier', 'المندوب'), 'type' => 'select', 'show' => 'courier'],
                ['key' => 'payment', 'label' => self::L('Payment', 'الدفع'), 'readonly' => true],
                ['key' => 'subtotal', 'label' => self::L('Items subtotal', 'المجموع الفرعي'), 'fmt' => 'egp', 'readonly' => true],
                ['key' => 'fee', 'label' => self::L('Delivery fee', 'رسوم التوصيل'), 'fmt' => 'egp', 'readonly' => true],
                ['key' => 'total', 'label' => self::L('Total', 'الإجمالي'), 'fmt' => 'egp', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
                ['key' => 'stage', 'label' => self::L('Stage', 'المرحلة'), 'readonly' => true],
                ['key' => 'date', 'label' => self::L('Date', 'التاريخ'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'notes', 'label' => self::L('Customer note', 'ملاحظة العميل'), 'readonly' => true, 'wide' => true],
            ],
            'lines' => ['title' => self::L('Items', 'المنتجات'), 'fmt' => 'egp'],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases([
            'new' => "o.order_status = 'pending'",
            'onway' => "o.order_status = 'picked_up'",
            'delivered' => "o.order_status in ('delivered', 'refund_request_canceled')",
            'cancelled' => "o.order_status in ('canceled', 'failed')",
            'refundreq' => "o.order_status = 'refund_requested'",
            'refunded' => "o.order_status = 'refunded'",
        ], 'preparing');
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('orders as o')
            ->join('stores as s', 's.id', '=', 'o.store_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('delivery_men as d', 'd.id', '=', 'o.delivery_man_id')
            ->leftJoin('zones as z', 'z.id', '=', 's.zone_id')
            ->select('o.id', 'o.order_status', 'o.payment_method', 'o.payment_status', 'o.order_amount', 'o.delivery_charge',
                'o.created_at', 'o.estimated_delivery_at', 'o.order_note', 'o.module_id', 'o.delivery_address', 'o.delivery_man_id', 'o.store_id',
                's.name as store_name', 's.zone_id', 's.logo', 'z.name as zone_name', 'u.f_name', 'u.l_name', 'u.phone as user_phone', 'u.image as user_image', 'o.user_id',
                'd.f_name as dm_f', 'd.l_name as dm_l')
            ->selectRaw('(' . $this->statusSql() . ') as st')
            ->selectRaw('(select r.refund_status from refunds r where r.order_id = o.id order by r.id desc limit 1) as refund_status');
        Images::selectDisk($q, Store::class, 's.id', 'logo', 'logo_disk');
        Images::selectDisk($q, User::class, 'u.id', 'image', 'user_disk');
        if ($zone = Scope::adminZone()) {
            $q->where('s.zone_id', $zone);
        }
        return self::module($q, $mod, 'o.module_id');
    }

    protected function row(object $r): array
    {
        $address = json_decode($r->delivery_address ?? '', true) ?: [];
        $customer = self::name($r->f_name, $r->l_name) ?: ($address['contact_person_name'] ?? translate('messages.guest_user'));
        $courier = self::name($r->dm_f, $r->dm_l);
        return [
            'id' => $r->id,
            'module' => Scope::code($r->module_id),
            'customer' => $customer,
            'phone' => $r->user_phone ?: ($address['contact_person_number'] ?? ''),
            'merchant' => $r->store_name,
            'merchant_id' => $r->store_id,
            'customer_id' => $r->user_id,
            'img' => Images::url('store', $r->logo, $r->logo_disk),
            'avatars' => ['customer' => Images::url('profile', $r->user_image, $r->user_disk)],
            'zone' => $r->zone_name,
            'courier' => $courier ?: 'Unassigned',
            'courier_id' => $r->delivery_man_id,
            'payment' => ucwords(str_replace('_', ' ', (string) $r->payment_method)),
            'fee' => (float) $r->delivery_charge,
            'total' => (float) $r->order_amount,
            'status' => $r->st,
            'stage' => ucwords(str_replace('_', ' ', (string) $r->order_status)),
            'raw_status' => $r->order_status,
            // A partly refunded order stays delivered; it can't be refunded again.
            'refunded_items' => $r->refund_status === 'approved',
            'late' => self::lateBy($r),
            'date' => self::date($r->created_at),
            'notes' => (string) $r->order_note,
        ];
    }

    public function find(int|string $id): ?array
    {
        $row = parent::find($id);
        if ($row) {
            $row['subtotal'] = (float) DB::table('order_details')->where('order_id', $id)
                ->sum(DB::raw('price * quantity + coalesce(total_add_on_price, 0)'));
            $row['photos'] = Refund::where('order_id', $id)->latest('id')->first()?->image_full_url ?? [];
        }
        return $row;
    }

    protected function lines(array $row): array
    {
        return DB::table('order_details')->where('order_id', $row['id'])->get()
            ->map(function ($d) {
                $item = json_decode($d->item_details ?? '', true) ?: [];
                return [$item['name'] ?? ('#' . $d->item_id), '× ' . $d->quantity, (float) $d->price * $d->quantity + (float) $d->total_add_on_price];
            })
            ->concat($this->complaintLines((int) $row['id']))
            ->all();
    }

    /** OD-2: the customer's complaint under the items: what, why, and the refund. */
    private function complaintLines(int $orderId): array
    {
        $refund = Refund::where('order_id', $orderId)->latest('id')->first();
        if (!$refund) {
            return [];
        }
        $ar = app()->getLocale() === 'ar';
        $state = ['pending' => $ar ? 'بانتظار القرار' : 'waiting for you', 'approved' => $ar ? 'تم الرد' : 'refunded', 'rejected' => $ar ? 'مرفوضة' : 'rejected'][$refund->refund_status] ?? $refund->refund_status;
        $lines = [[($ar ? '⚠ شكوى · ' : '⚠ Complaint · ') . $state, trim($refund->customer_reason . ($refund->customer_note ? ' — ' . $refund->customer_note : '')), (float) $refund->refund_amount]];
        foreach ($refund->items ?: [] as $item) {
            $lines[] = ['   ↳ ' . ($item['name'] ?? ''), '× ' . ($item['quantity'] ?? 1), (float) ($item['amount'] ?? 0)];
        }
        if ($refund->store_amount !== null) {
            $lines[] = [$ar ? '   المتجر يدفع' : '   Store pays', '', (float) $refund->store_amount];
        }
        return $lines;
    }

    public function options(?array $row = null): array
    {
        $couriers = DB::table('delivery_men')->where('status', 1)->where('application_status', 'approved')
            ->when(Scope::adminZone(), fn ($q, $z) => $q->where('zone_id', $z))
            ->orderBy('f_name')->get(['id', 'f_name', 'l_name']);
        return ['courier_id' => $couriers->map(fn ($c) => ['value' => $c->id, 'label' => self::name($c->f_name, $c->l_name)])->prepend(['value' => '', 'label' => 'Unassigned'])->all()];
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.order.details', ['id' => $row['id']]);
    }

    /** Minutes past the estimated delivery time, for orders still on their way to the customer. */
    private static function lateBy(object $r): ?int
    {
        if (!$r->estimated_delivery_at || !in_array($r->st, ['new', 'preparing', 'onway'], true)) {
            return null;
        }
        $late = (int) floor((time() - strtotime($r->estimated_delivery_at)) / 60);
        return $late > 0 ? $late : null;
    }

    /** The next step in the classic flow, as the action dispatch would take it. */
    private const NEXT = [
        'pending' => ['confirmed', 'Confirm order', 'تأكيد الطلب'],
        'accepted' => ['handover', 'Ready for pickup', 'جاهز للاستلام'],
        'confirmed' => ['handover', 'Ready for pickup', 'جاهز للاستلام'],
        'processing' => ['handover', 'Ready for pickup', 'جاهز للاستلام'],
        'handover' => ['picked_up', 'Mark picked up', 'تم الاستلام'],
        'picked_up' => ['delivered', 'Mark delivered', 'تم التوصيل'],
    ];

    protected function actions(): array
    {
        $advance = [];
        foreach (self::NEXT as $from => [$to, $en, $ar]) {
            $advance[] = ['key' => 'to_' . $from, 'label' => self::L($en, $ar), 'tone' => 'primary',
                'when' => fn ($x) => $x['raw_status'] === $from,
                'run' => fn ($x) => $this->setStatus($x['id'], $to)];
        }
        return [...$advance,
            ['key' => 'refund', 'label' => self::L('Issue refund', 'استرداد المبلغ'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'delivered' && !$x['refunded_items'],
                'run' => function ($x) {
                    if (BusinessSetting::where('key', 'refund_active_status')->value('value') != 1) {
                        return self::fail(translate('Refund Option is not active. Please active it from Refund Settings'));
                    }
                    return $this->setStatus($x['id'], 'refunded');
                }],
            // OD-2: the customer's complaint. Approving refunds the picked items
            // (or the whole order) and the store pays its share.
            ['key' => 'approve_refund', 'label' => self::L('Approve refund', 'قبول الاسترداد'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'refundreq',
                'run' => function ($x) {
                    if (BusinessSetting::where('key', 'refund_active_status')->value('value') != 1) {
                        return self::fail(translate('Refund Option is not active. Please active it from Refund Settings'));
                    }
                    return $this->setStatus($x['id'], 'refunded');
                }],
            ['key' => 'reject_refund', 'label' => self::L('Reject complaint', 'رفض الشكوى'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'refundreq',
                'run' => fn ($x) => ClassicAction::run(OrderController::class, 'order_refund_rejection', ['order_id' => $x['id']], [], 'POST')],
            ['key' => 'cancel', 'label' => self::L('Cancel', 'إلغاء'), 'tone' => 'danger',
                'when' => fn ($x) => in_array($x['status'], self::ACTIVE, true),
                'run' => fn ($x) => $this->setStatus($x['id'], 'canceled', ['reason' => 'Canceled by admin']),
            ],
        ];
    }

    /** Moves an order through the classic status flow (stock, wallet, pushes). */
    public function setStatus(int $id, string $status, array $extra = []): array
    {
        return ClassicAction::run(OrderController::class, 'status', ['id' => $id, 'order_status' => $status] + $extra, [], 'GET');
    }

    public function assign(int $id, int $courierId): array
    {
        return ClassicAction::run(OrderController::class, 'add_delivery_man', [], ['order_id' => $id, 'delivery_man_id' => $courierId], 'GET');
    }

    public function update(int|string $id, array $data): array
    {
        $row = $this->find($id);
        $courier = (int) ($data['courier_id'] ?? 0);
        if (!$row || !$courier || $courier === (int) $row['courier_id']) {
            return self::ok(translate('messages.updated_successfully'));
        }
        return $this->assign((int) $id, $courier);
    }
}
