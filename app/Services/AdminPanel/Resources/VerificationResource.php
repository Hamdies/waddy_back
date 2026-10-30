<?php

namespace App\Services\AdminPanel\Resources;

use App\Models\Store;
use App\Models\StoreDocument;
use App\Services\AdminPanel\Images;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use App\Services\StoreDocumentService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * MA-4: store documents and payout account changes waiting for support.
 * Open one, read the file, Edit to type its number, issuer and expiry, then
 * Approve. Reject sends the note to the store.
 */
class VerificationResource extends Resource
{
    public ?string $permission = 'store';
    protected string $idColumn = 'd.id';
    protected ?string $zoneColumn = 's.zone_id';
    protected ?string $merchantColumn = 's.id';
    protected ?string $merchantNameSql = 's.name';
    protected ?string $dateColumn = 'd.created_at';
    protected array $search = ['s.name', 'd.number', 'd.holder_name'];
    protected array $sorts = ['id' => 'd.id', 'store' => 's.name', 'date' => 'd.created_at'];
    protected string $defaultSort = "field(d.status, 'pending', 'rejected', 'verified'), d.id desc";

    public function config(): array
    {
        return [
            'label' => self::L('Verification', 'التحقق'), 'one' => self::L('Document', 'مستند'),
            'titleKey' => 'store', 'subKey' => 'typeLabel', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'merchantKey' => true,
            'editable' => true, 'search' => ['store'],
            'filter' => ['key' => 'status', 'options' => ['pending', 'verified', 'rejected']],
            'columns' => [
                ['key' => 'id', 'label' => self::L('#', '#'), 'w' => '0.5fr', 'strong' => true, 'sort' => 'id'],
                ['key' => 'store', 'label' => self::L('Store', 'المتجر'), 'sub' => 'typeLabel', 'w' => '1.8fr', 'sort' => 'store'],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'fmt' => 'module', 'w' => '0.8fr', 'allOnly' => true],
                ['key' => 'summary', 'label' => self::L('Details', 'التفاصيل'), 'w' => '1.6fr'],
                ['key' => 'date', 'label' => self::L('Sent', 'تاريخ الإرسال'), 'fmt' => 'date', 'w' => '0.8fr', 'sort' => 'date'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.8fr'],
            ],
            'fields' => [
                ['key' => 'store', 'label' => self::L('Store', 'المتجر'), 'readonly' => true, 'link' => ['merchants', 'store_id']],
                ['key' => 'typeLabel', 'label' => self::L('Document', 'المستند'), 'readonly' => true],
                ['key' => 'number', 'label' => self::L('Number / IBAN', 'الرقم / IBAN')],
                ['key' => 'issued_by', 'label' => self::L('Issued by / bank', 'جهة الإصدار / البنك')],
                ['key' => 'holder_name', 'label' => self::L('Name on it', 'الاسم')],
                ['key' => 'expires_at', 'label' => self::L('Valid to', 'صالح حتى'), 'type' => 'date', 'fmt' => 'date'],
                ['key' => 'current', 'label' => self::L('Replaces', 'بدلاً من'), 'readonly' => true, 'wide' => true],
                ['key' => 'note', 'label' => self::L('Note to the store (sent on reject)', 'ملاحظة للمتجر (تُرسل عند الرفض)'), 'type' => 'textarea', 'wide' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return "d.status";
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('store_documents as d')
            ->join('stores as s', 's.id', '=', 'd.store_id')
            ->leftJoin('modules as m', 'm.id', '=', 's.module_id')
            ->select('d.*', 's.name as store_name', 's.logo', 's.module_id', 's.vendor_id', 'm.module_type')
            ->selectRaw('(' . $this->statusSql() . ') as st');
        Images::selectDisk($q, Store::class, 's.id', 'logo', 'logo_disk');
        if ($zone = Scope::adminZone()) {
            $q->where('s.zone_id', $zone);
        }
        return self::module($q, $mod, 's.module_id');
    }

    protected function row(object $r): array
    {
        $details = json_decode($r->details ?? '', true) ?: [];
        $bank = $r->type === 'bank';
        $label = StoreDocumentService::label($r->type, $r->module_type);
        $number = $bank ? ($details['account_no'] ?? null) : $r->number;
        $issuer = $bank ? ($details['bank_name'] ?? null) : $r->issued_by;
        $holder = $bank ? ($details['holder_name'] ?? null) : $r->holder_name;
        return [
            'id' => $r->id,
            'store' => $r->store_name,
            'store_id' => $r->store_id,
            'vendor_id' => $r->vendor_id,
            'type' => $r->type,
            'typeLabel' => $label[0],
            'img' => $img = Images::url('store', $r->logo, $r->logo_disk),
            'avatars' => ['store' => $img],
            'photoUrl' => $r->file ? route('admin.panel.document-file', ['id' => $r->id]) : null,
            'module' => Scope::code($r->module_id),
            'number' => $number,
            'issued_by' => $issuer,
            'holder_name' => $holder,
            'expires_at' => $r->expires_at,
            'summary' => implode(' · ', array_filter([$issuer, $number, $r->expires_at ? 'to ' . $r->expires_at : null])) ?: ($r->file ? 'File to read' : '—'),
            'current' => $bank ? $this->currentBank($r->vendor_id) : null,
            'note' => $r->note,
            'date' => self::date($r->created_at),
            'status' => $r->st,
        ];
    }

    private function currentBank(?int $vendorId): string
    {
        $v = $vendorId ? DB::table('vendors')->where('id', $vendorId)->first(['bank_name', 'holder_name', 'account_no']) : null;
        return $v && $v->bank_name ? trim("{$v->bank_name} · {$v->holder_name} · {$v->account_no}") : 'No account yet';
    }

    public function update(int|string $id, array $data): array
    {
        $doc = StoreDocument::find($id);
        if (!$doc) {
            return self::fail(translate('messages.not_found'));
        }
        $v = Validator::make($data, [
            'number' => 'nullable|string|max:100',
            'issued_by' => 'nullable|string|max:150',
            'holder_name' => 'nullable|string|max:150',
            'expires_at' => 'nullable|date',
            'note' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        if ($doc->type === 'bank') {
            // A typo in what the store typed can be fixed before approving.
            $doc->details = array_merge($doc->details ?? [], array_filter([
                'account_no' => $data['number'] ?? null,
                'bank_name' => $data['issued_by'] ?? null,
                'holder_name' => $data['holder_name'] ?? null,
            ]));
        } else {
            $doc->fill([
                'number' => $data['number'] ?? null,
                'issued_by' => $data['issued_by'] ?? null,
                'holder_name' => $data['holder_name'] ?? null,
                'expires_at' => $data['expires_at'] ?: null,
            ]);
        }
        $doc->note = $data['note'] ?? null;
        $doc->save();
        return self::ok(translate('messages.updated_successfully'));
    }

    protected function actions(): array
    {
        $admin = fn () => (int) auth('admin')->id();
        return [
            ['key' => 'approve', 'label' => self::L('Approve', 'قبول'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'pending',
                'run' => function ($x) use ($admin) {
                    if ($x['type'] !== 'bank' && $x['type'] !== 'commercial_register' && !$x['expires_at']) {
                        return self::fail('Edit it first: add the expiry date from the file.');
                    }
                    StoreDocumentService::approve(StoreDocument::find($x['id']), $admin());
                    return self::ok('Approved · the store was told');
                }],
            ['key' => 'reject', 'label' => self::L('Reject', 'رفض'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'pending',
                'run' => function ($x) use ($admin) {
                    if (trim((string) $x['note']) === '') {
                        return self::fail('Edit it first: write a note telling the store what to fix.');
                    }
                    StoreDocumentService::reject(StoreDocument::find($x['id']), $admin(), $x['note']);
                    return self::ok('Rejected · the store was told');
                }],
        ];
    }
}
