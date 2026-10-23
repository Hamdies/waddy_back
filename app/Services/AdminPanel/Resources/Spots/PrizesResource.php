<?php

namespace App\Services\AdminPanel\Resources\Spots;

use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Resource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\PlacesToVisit\Entities\PlacePrize;
use Modules\PlacesToVisit\Http\Controllers\Admin\PlacePrizeController;

/** Voter vouchers. Never reissued or extended; the admin can only redeem one by hand. */
class PrizesResource extends Resource
{
    use SpotsQueries;

    protected string $idColumn = 'z.id';
    protected ?string $merchantColumn = 'z.place_id';
    protected ?string $merchantNameSql = 'pt.title';
    protected ?string $dateColumn = 'z.created_at';
    protected array $search = ['z.code', 'u.f_name', 'u.l_name', 'pt.title', 'z.period'];
    protected array $sorts = ['code' => 'z.code', 'place' => 'pt.title', 'period' => 'z.period', 'valueCap' => 'z.value_cap', 'expires' => 'z.expires_at'];

    public function config(): array
    {
        return [
            'label' => self::L('Vouchers', 'القسائم'), 'one' => self::L('Voucher', 'قسيمة'),
            'titleKey' => 'code', 'subKey' => 'winner', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'merchantKey' => true, 'merchantLabel' => self::L('Place', 'المكان'), 'square' => true,
            'search' => ['code', 'winner', 'place', 'period'],
            'filter' => ['key' => 'status', 'options' => ['active', 'redeemed', 'expired']],
            'columns' => [
                ['key' => 'code', 'label' => self::L('Code', 'الكود'), 'sub' => 'winner', 'w' => '1.5fr', 'strong' => true, 'sort' => 'code', 'mono' => true],
                ['key' => 'place', 'label' => self::L('Place', 'المكان'), 'w' => '1.3fr', 'sort' => 'place'],
                ['key' => 'period', 'label' => self::L('Week', 'الأسبوع'), 'w' => '0.8fr', 'muted' => true, 'sort' => 'period'],
                ['key' => 'valueCap', 'label' => self::L('Up to', 'حتى'), 'fmt' => 'egp', 'w' => '0.7fr', 'sort' => 'valueCap'],
                ['key' => 'expires', 'label' => self::L('Expires', 'ينتهي'), 'fmt' => 'date', 'w' => '0.7fr', 'sort' => 'expires'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.8fr'],
            ],
            'fields' => [
                ['key' => 'code', 'label' => self::L('Code', 'الكود'), 'readonly' => true],
                ['key' => 'winner', 'label' => self::L('Winner', 'الفائز'), 'readonly' => true],
                ['key' => 'masked', 'label' => self::L('Shown publicly as', 'يظهر للعامة باسم'), 'readonly' => true],
                ['key' => 'place', 'label' => self::L('Place', 'المكان'), 'readonly' => true],
                ['key' => 'period', 'label' => self::L('Week', 'الأسبوع'), 'readonly' => true],
                ['key' => 'valueCap', 'label' => self::L('Free item up to', 'منتج مجاني حتى'), 'fmt' => 'egp', 'readonly' => true],
                ['key' => 'issued', 'label' => self::L('Issued', 'الإصدار'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'expires', 'label' => self::L('Expires', 'ينتهي'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'reminderSent', 'label' => self::L('24 h reminder sent', 'تم إرسال التذكير'), 'fmt' => 'bool', 'readonly' => true],
                ['key' => 'redeemedAt', 'label' => self::L('Redeemed', 'تاريخ الاستخدام'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['redeemed' => "z.status = 'redeemed'", 'expired' => "z.status = 'expired' or z.expires_at < now()"], 'active');
    }

    protected function query(string $mod): Builder
    {
        return $this->joinPlace(DB::table('place_prizes as z'), 'z.place_id')
            ->leftJoin('users as u', 'u.id', '=', 'z.user_id')
            ->select('z.id', 'z.code', 'z.period', 'z.value_cap', 'z.created_at', 'z.expires_at', 'z.reminder_sent_at', 'z.redeemed_at',
                'pt.title', 'u.f_name', 'u.l_name')
            ->selectRaw('(' . $this->statusSql() . ') as st');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'code' => $r->code,
            'winner' => self::name($r->f_name, $r->l_name) ?: '—',
            'masked' => self::masked($r->f_name, $r->l_name),
            'place' => $r->title,
            'module' => 'spots',
            'period' => $r->period,
            'valueCap' => (float) $r->value_cap,
            'issued' => self::date($r->created_at),
            'expires' => self::date($r->expires_at),
            'reminderSent' => (bool) $r->reminder_sent_at,
            'redeemedAt' => self::date($r->redeemed_at),
            'status' => $r->st,
        ];
    }

    protected function actions(): array
    {
        return [
            ['key' => 'redeem', 'label' => self::L('Redeem manually', 'استخدام يدوي'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'active',
                'run' => function ($x) {
                    $result = ClassicAction::run(PlacePrizeController::class, 'redeem', [], ['prize' => PlacePrize::find($x['id'])]);
                    return $result['ok'] ? self::ok('Redeemed. Vouchers are never reissued or extended.') : $result;
                }],
        ];
    }
}
