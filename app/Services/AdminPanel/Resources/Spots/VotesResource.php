<?php

namespace App\Services\AdminPanel\Resources\Spots;

use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Resource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\PlacesToVisit\Entities\PlaceVote;
use Modules\PlacesToVisit\Http\Controllers\Admin\LeaderboardController;
use Modules\PlacesToVisit\Services\LeaderboardService;

/** Weekly votes. A flagged vote stops counting and takes its voter out of that week's draw pool. */
class VotesResource extends Resource
{
    use SpotsQueries;

    protected string $idColumn = 'v.id';
    protected ?string $zoneColumn = 'p.zone_id';
    protected string $zoneTable = 'place_zones';
    protected ?string $merchantColumn = 'v.place_id';
    protected ?string $merchantNameSql = 'pt.title';
    protected ?string $dateColumn = 'v.created_at';
    protected array $search = ['u.f_name', 'u.l_name', 'pt.title', 'v.period'];
    protected array $sorts = ['voter' => 'u.f_name', 'place' => 'pt.title', 'zone' => 'pz.name', 'period' => 'v.period', 'date' => 'v.created_at'];

    public function config(): array
    {
        return [
            'label' => self::L('Votes', 'الأصوات'), 'one' => self::L('Vote', 'صوت'),
            'titleKey' => 'voter', 'subKey' => 'place', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'zoneKey' => true, 'merchantKey' => true, 'merchantLabel' => self::L('Place', 'المكان'),
            'deletable' => true, 'search' => ['voter', 'place'],
            'filter' => ['key' => 'status', 'options' => ['counted', 'flagged']],
            'columns' => [
                ['key' => 'voter', 'label' => self::L('Voter', 'المصوت'), 'sub' => 'ref', 'w' => '1.5fr', 'strong' => true, 'sort' => 'voter'],
                ['key' => 'place', 'label' => self::L('Place', 'المكان'), 'w' => '1.4fr', 'sort' => 'place'],
                ['key' => 'zone', 'label' => self::L('Zone', 'المنطقة'), 'w' => '1fr', 'sort' => 'zone'],
                ['key' => 'period', 'label' => self::L('Week', 'الأسبوع'), 'w' => '0.8fr', 'muted' => true, 'sort' => 'period'],
                ['key' => 'withReview', 'label' => self::L('Review', 'مراجعة'), 'fmt' => 'bool', 'w' => '0.7fr'],
                ['key' => 'date', 'label' => self::L('Cast', 'التاريخ'), 'fmt' => 'date', 'w' => '0.7fr', 'sort' => 'date'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.8fr'],
            ],
            'fields' => [
                ['key' => 'voter', 'label' => self::L('Voter', 'المصوت'), 'readonly' => true],
                ['key' => 'place', 'label' => self::L('Place', 'المكان'), 'readonly' => true],
                ['key' => 'zone', 'label' => self::L('Zone', 'المنطقة'), 'readonly' => true],
                ['key' => 'period', 'label' => self::L('Week', 'الأسبوع'), 'readonly' => true],
                ['key' => 'withReview', 'label' => self::L('Left a review', 'ترك مراجعة'), 'fmt' => 'bool', 'readonly' => true],
                ['key' => 'date', 'label' => self::L('Cast', 'التاريخ'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['flagged' => 'v.is_flagged = 1'], 'counted');
    }

    protected function query(string $mod): Builder
    {
        return $this->joinPlace(DB::table('place_votes as v'), 'v.place_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->select('v.id', 'v.period', 'v.review', 'v.created_at', 'v.place_id', 'pt.title', 'pz.name as zone_name', 'u.f_name', 'u.l_name')
            ->selectRaw('(' . $this->statusSql() . ') as st');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'ref' => 'V-' . $r->id,
            'voter' => self::name($r->f_name, $r->l_name) ?: '—',
            'place' => $r->title,
            'module' => 'spots',
            'zone' => $r->zone_name,
            'period' => $r->period,
            'withReview' => trim((string) $r->review) !== '',
            'date' => self::date($r->created_at),
            'status' => $r->st,
        ];
    }

    protected function actions(): array
    {
        $toggle = function ($x) {
            $result = ClassicAction::run(LeaderboardController::class, 'toggleFlag', [], ['vote' => PlaceVote::find($x['id'])], 'GET');
            app(LeaderboardService::class)->clearCache();
            return $result;
        };
        return [
            ['key' => 'flag', 'label' => self::L('Flag vote', 'الإبلاغ عن الصوت'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'counted',
                'run' => fn ($x) => ($r = $toggle($x))['ok'] ? self::ok('Flagged. This voter is out of the draw pool.') : $r],
            ['key' => 'unflag', 'label' => self::L('Unflag', 'إلغاء الإبلاغ'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'flagged',
                'run' => fn ($x) => ($r = $toggle($x))['ok'] ? self::ok('Vote counts again') : $r],
        ];
    }

    public function delete(int|string $id): array
    {
        return ClassicAction::run(LeaderboardController::class, 'deleteVote', [], ['vote' => PlaceVote::find($id)], 'DELETE');
    }
}
