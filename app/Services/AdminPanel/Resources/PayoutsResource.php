<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\VendorController;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Store withdraw requests. Approving moves the amount from pending to withdrawn in the store wallet. */
class PayoutsResource extends Resource
{
    public ?string $permission = 'withdraw_list';
    protected string $idColumn = 'w.id';
    protected ?string $merchantColumn = 's.id';
    protected ?string $merchantNameSql = 's.name';
    protected ?string $dateColumn = 'w.created_at';
    protected array $search = ['s.name', 'd.f_name', 'd.l_name', 'w.transaction_note'];
    protected array $sorts = ['id' => 'w.id', 'payee' => 's.name', 'amount' => 'w.amount', 'date' => 'w.created_at'];

    public function config(): array
    {
        return [
            'label' => self::L('Payouts', 'المدفوعات'), 'one' => self::L('Payout', 'دفعة'),
            'titleKey' => 'payee', 'subKey' => 'date', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'merchantKey' => true, 'merchantLabel' => self::L('Payee', 'المستفيد'),
            'search' => ['payee'],
            'filter' => ['key' => 'status', 'options' => ['pending', 'paid', 'denied']],
            'columns' => [
                ['key' => 'id', 'label' => self::L('Payout', 'الدفعة'), 'w' => '0.8fr', 'strong' => true, 'sort' => 'id'],
                ['key' => 'payee', 'label' => self::L('Payee', 'المستفيد'), 'sub' => 'payeeType', 'w' => '1.6fr', 'sort' => 'payee'],
                ['key' => 'date', 'label' => self::L('Requested', 'تاريخ الطلب'), 'fmt' => 'date', 'w' => '1fr', 'sort' => 'date'],
                ['key' => 'method', 'label' => self::L('Method', 'الطريقة'), 'w' => '1fr'],
                ['key' => 'amount', 'label' => self::L('Amount', 'المبلغ'), 'fmt' => 'egp', 'w' => '0.9fr', 'sort' => 'amount'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.8fr'],
            ],
            'fields' => [
                ['key' => 'payee', 'label' => self::L('Payee', 'المستفيد'), 'readonly' => true],
                ['key' => 'payeeType', 'label' => self::L('Type', 'النوع'), 'readonly' => true],
                ['key' => 'amount', 'label' => self::L('Amount (EGP)', 'المبلغ (ج.م)'), 'fmt' => 'egp', 'readonly' => true],
                ['key' => 'method', 'label' => self::L('Method', 'الطريقة'), 'readonly' => true],
                ['key' => 'balance', 'label' => self::L('Wallet: earned / withdrawn', 'المحفظة: الأرباح / المسحوب'), 'readonly' => true],
                ['key' => 'date', 'label' => self::L('Requested', 'تاريخ الطلب'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
                ['key' => 'reference', 'label' => self::L('Note', 'ملاحظة'), 'readonly' => true, 'wide' => true],
            ],
            'lines' => ['title' => self::L('Payout details', 'بيانات التحويل')],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['paid' => 'w.approved = 1', 'denied' => 'w.approved = 2'], 'pending');
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('withdraw_requests as w')
            ->leftJoin(DB::raw('(select vendor_id, min(id) as store_id from stores group by vendor_id) as vs'), 'vs.vendor_id', '=', 'w.vendor_id')
            ->leftJoin('stores as s', 's.id', '=', 'vs.store_id')
            ->leftJoin('delivery_men as d', 'd.id', '=', 'w.delivery_man_id')
            ->leftJoin('withdrawal_methods as wm', 'wm.id', '=', 'w.withdrawal_method_id')
            ->leftJoin('store_wallets as sw', 'sw.vendor_id', '=', 'w.vendor_id')
            ->select('w.id', 'w.vendor_id', 'w.delivery_man_id', 'w.amount', 'w.approved', 'w.transaction_note', 'w.created_at',
                'w.withdrawal_method_fields', 's.name as store_name', 's.module_id', 'd.f_name', 'd.l_name', 'wm.method_name',
                'sw.total_earning', 'sw.total_withdrawn')
            ->selectRaw('(' . $this->statusSql() . ') as st');
        if ($zone = Scope::adminZone()) {
            $q->where('s.zone_id', $zone);
        }
        return self::module($q, $mod, 's.module_id');
    }

    protected function row(object $r): array
    {
        $isStore = (bool) $r->vendor_id;
        return [
            'id' => $r->id,
            'payee' => $isStore ? $r->store_name : self::name($r->f_name, $r->l_name),
            'payeeType' => $isStore ? 'Merchant' : 'Courier',
            'isStore' => $isStore,
            'module' => $isStore ? Scope::code($r->module_id) : 'all',
            'method' => $r->method_name ?: 'Bank transfer',
            'methodFields' => json_decode($r->withdrawal_method_fields ?? '', true) ?: [],
            'amount' => (float) $r->amount,
            'balance' => $isStore ? 'EGP ' . number_format((float) $r->total_earning) . ' / ' . number_format((float) $r->total_withdrawn) : '—',
            'reference' => (string) $r->transaction_note,
            'date' => self::date($r->created_at),
            'status' => $r->st,
        ];
    }

    protected function lines(array $row): array
    {
        return collect($row['methodFields'])->map(fn ($v, $k) => [ucwords(str_replace('_', ' ', $k)), '', is_scalar($v) ? (string) $v : ''])->values()->all();
    }

    protected function classicUrl(array $row): ?string
    {
        return $row['isStore'] ? route('admin.transactions.store.withdraw_view', ['withdraw_id' => $row['id'], 'seller_id' => DB::table('withdraw_requests')->where('id', $row['id'])->value('vendor_id')]) : null;
    }

    protected function actions(): array
    {
        $decide = fn ($id, $approved) => ClassicAction::run(VendorController::class, 'withdrawStatus', ['approved' => $approved, 'note' => 'Admin panel'], ['id' => $id]);
        return [
            ['key' => 'pay', 'label' => self::L('Mark as paid', 'تحديد كمدفوع'), 'tone' => 'primary',
                'when' => fn ($x) => $x['isStore'] && $x['status'] === 'pending', 'run' => fn ($x) => $decide($x['id'], 1)],
            ['key' => 'deny', 'label' => self::L('Deny', 'رفض'), 'tone' => 'danger',
                'when' => fn ($x) => $x['isStore'] && $x['status'] === 'pending', 'run' => fn ($x) => $decide($x['id'], 2)],
        ];
    }
}
