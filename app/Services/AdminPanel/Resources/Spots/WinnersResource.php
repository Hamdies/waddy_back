<?php

namespace App\Services\AdminPanel\Resources\Spots;

use App\Services\AdminPanel\Resource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Hall of Fame: one row per crowned scope and week. A zone champion gets the trophy only; the draw runs on the overall winner. */
class WinnersResource extends Resource
{
    use SpotsQueries;

    protected string $idColumn = 'w.id';
    protected ?string $merchantColumn = 'w.place_id';
    protected ?string $merchantNameSql = 'pt.title';
    protected ?string $dateColumn = 'w.created_at';
    protected array $search = ['pt.title', 'w.period', 'wz.name'];
    protected array $sorts = ['period' => 'w.period', 'place' => 'pt.title', 'votes' => 'w.votes_count', 'entrants' => 'entrants', 'prizes' => 'prizes', 'crownedAt' => 'w.created_at'];
    protected string $defaultSort = "w.period regexp '^[0-9]{4}-W[0-9]{2}$' desc, w.period desc, w.zone_id is not null, w.id desc";

    public function config(): array
    {
        return [
            'label' => self::L('Hall of Fame', 'قاعة المشاهير'), 'one' => self::L('Champion', 'بطل'),
            'titleKey' => 'place', 'subKey' => 'period', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'merchantKey' => true, 'merchantLabel' => self::L('Place', 'المكان'), 'square' => true,
            'search' => ['place', 'period', 'scope'],
            'filter' => ['key' => 'status', 'options' => ['crowned', 'malformed']],
            'columns' => [
                ['key' => 'period', 'label' => self::L('Week', 'الأسبوع'), 'w' => '0.9fr', 'strong' => true, 'sort' => 'period'],
                ['key' => 'place', 'label' => self::L('Champion', 'البطل'), 'sub' => 'scope', 'w' => '1.6fr', 'sort' => 'place'],
                ['key' => 'votes', 'label' => self::L('Votes', 'الأصوات'), 'fmt' => 'int', 'w' => '0.6fr', 'sort' => 'votes'],
                ['key' => 'entrants', 'label' => self::L('Draw pool', 'مجموعة السحب'), 'fmt' => 'int', 'w' => '0.8fr', 'sort' => 'entrants'],
                ['key' => 'prizes', 'label' => self::L('Vouchers', 'القسائم'), 'fmt' => 'int', 'w' => '0.6fr', 'sort' => 'prizes'],
                ['key' => 'crownedAt', 'label' => self::L('Crowned', 'التتويج'), 'fmt' => 'date', 'w' => '0.8fr', 'sort' => 'crownedAt'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '1fr'],
            ],
            'fields' => [
                ['key' => 'period', 'label' => self::L('Week', 'الأسبوع'), 'readonly' => true],
                ['key' => 'scope', 'label' => self::L('Scope', 'النطاق'), 'readonly' => true],
                ['key' => 'place', 'label' => self::L('Champion', 'البطل'), 'readonly' => true],
                ['key' => 'votes', 'label' => self::L('Votes', 'الأصوات'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'entrants', 'label' => self::L('Draw pool', 'مجموعة السحب'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'prizes', 'label' => self::L('Vouchers drawn', 'القسائم'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'crownedAt', 'label' => self::L('Crowned', 'التتويج'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
            'lines' => ['title' => self::L('Draw winners', 'الفائزون')],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['crowned' => "w.period regexp '^[0-9]{4}-W[0-9]{2}$'"], 'malformed');
    }

    protected function query(string $mod): Builder
    {
        return $this->joinPlace(DB::table('place_winners as w'), 'w.place_id')
            ->leftJoin('place_zones as wz', 'wz.id', '=', 'w.zone_id')
            ->select('w.id', 'w.period', 'w.zone_id', 'w.place_id', 'w.votes_count', 'w.created_at', 'pt.title', 'pz.name as place_zone', 'wz.name as scope_zone')
            ->selectRaw('(select max(e.total_entrants) from place_draw_entrants e where e.place_winner_id = w.id) as entrants')
            ->selectRaw('(select count(*) from place_prizes z where z.place_winner_id = w.id) as prizes')
            ->selectRaw('(' . $this->statusSql() . ') as st');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'period' => $r->period,
            'scope' => $r->zone_id ? ($r->scope_zone ?: 'Zone') : 'Overall',
            'overall' => !$r->zone_id,
            'place' => $r->title,
            'place_id' => $r->place_id,
            'zone' => $r->place_zone,
            'module' => 'spots',
            'votes' => (int) $r->votes_count,
            'entrants' => (int) $r->entrants,
            'prizes' => (int) $r->prizes,
            'crownedAt' => self::date($r->created_at),
            'status' => $r->st,
        ];
    }

    protected function lines(array $row): array
    {
        return DB::table('place_prizes as z')->leftJoin('users as u', 'u.id', '=', 'z.user_id')
            ->where('z.place_winner_id', $row['id'])->orderBy('z.id')->get(['z.code', 'z.status', 'u.f_name', 'u.l_name'])
            ->map(fn ($p) => [self::name($p->f_name, $p->l_name) ?: '—', $p->code, ucfirst($p->status)])->all();
    }
}
