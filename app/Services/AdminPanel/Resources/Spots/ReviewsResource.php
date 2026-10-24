<?php

namespace App\Services\AdminPanel\Resources\Spots;

use App\Services\AdminPanel\Images;
use App\Services\AdminPanel\Resource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Place reviews. There is one switch, is_flagged, and it hides the review.
 * A review hidden by reaching the report threshold reads "flagged"; one an
 * admin hid with fewer reports reads "hidden".
 */
class ReviewsResource extends Resource
{
    use SpotsQueries;

    protected string $idColumn = 'r.id';
    protected ?string $merchantColumn = 'r.place_id';
    protected ?string $merchantNameSql = 'pt.title';
    protected ?string $dateColumn = 'r.created_at';
    protected array $search = ['pt.title', 'u.f_name', 'u.l_name', 'r.review'];
    protected array $sorts = ['place' => 'pt.title', 'rating' => 'r.rating', 'reports' => 'reports', 'date' => 'r.created_at'];

    public function config(): array
    {
        $threshold = self::threshold();
        return [
            'label' => self::L('Reviews', 'التقييمات'), 'one' => self::L('Review', 'تقييم'),
            'titleKey' => 'place', 'subKey' => 'user', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'merchantKey' => true, 'merchantLabel' => self::L('Place', 'المكان'), 'square' => true,
            'deletable' => true, 'search' => ['place', 'user', 'comment'],
            'filter' => ['key' => 'status', 'options' => ['published', 'flagged', 'hidden']],
            'columns' => [
                ['key' => 'place', 'label' => self::L('Place', 'المكان'), 'sub' => 'user', 'w' => '1.5fr', 'strong' => true, 'sort' => 'place'],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'stars', 'w' => '0.8fr', 'sort' => 'rating'],
                ['key' => 'comment', 'label' => self::L('Review', 'المراجعة'), 'w' => '2fr', 'muted' => true],
                ['key' => 'photo', 'label' => self::L('Photo', 'صورة'), 'fmt' => 'bool', 'w' => '0.5fr'],
                ['key' => 'reports', 'label' => self::L('Reports', 'البلاغات'), 'fmt' => 'int', 'w' => '0.6fr', 'sort' => 'reports'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.9fr'],
            ],
            'fields' => [
                ['key' => 'place', 'label' => self::L('Place', 'المكان'), 'readonly' => true],
                ['key' => 'user', 'label' => self::L('User', 'المستخدم'), 'readonly' => true],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'stars', 'readonly' => true],
                ['key' => 'reports', 'label' => self::L("Reports (auto-flag at $threshold)", "البلاغات (إبلاغ تلقائي عند $threshold)"), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'comment', 'label' => self::L('Review', 'المراجعة'), 'readonly' => true, 'wide' => true],
                ['key' => 'date', 'label' => self::L('Posted', 'التاريخ'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
            'lines' => ['title' => self::L('Reports', 'البلاغات')],
        ];
    }

    private static function threshold(): int
    {
        return (int) config('placestovisit.report_auto_flag_threshold', 3);
    }

    private const REPORTS = '(select count(*) from place_vote_reports vr where vr.vote_id = r.id)';

    protected function statusSql(): string
    {
        return self::cases([
            'published' => 'r.is_flagged = 0',
            'flagged' => self::REPORTS . ' >= ' . self::threshold(),
        ], 'hidden');
    }

    protected function query(string $mod): Builder
    {
        return $this->joinPlace(DB::table('place_reviews as r'), 'r.place_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->select('r.id', 'r.rating', 'r.review', 'r.image', 'r.created_at', 'pt.title', 'p.image as place_image', 'u.f_name', 'u.l_name')
            ->selectRaw(self::REPORTS . ' as reports')
            ->selectRaw('(' . $this->statusSql() . ') as st');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'place' => $r->title,
            'img' => $img = Images::url('places', $r->place_image),
            'avatars' => ['place' => $img],
            'photoUrl' => Images::url('places', $r->image),
            'module' => 'spots',
            'user' => self::name($r->f_name, $r->l_name) ?: '—',
            'rating' => (int) $r->rating,
            'comment' => (string) $r->review,
            'photo' => (bool) $r->image,
            'reports' => (int) $r->reports,
            'date' => self::date($r->created_at),
            'status' => $r->st,
        ];
    }

    protected function lines(array $row): array
    {
        return DB::table('place_vote_reports as vr')->leftJoin('users as u', 'u.id', '=', 'vr.reporter_id')
            ->where('vr.vote_id', $row['id'])->orderBy('vr.id')->get(['vr.reason', 'vr.created_at', 'u.f_name', 'u.l_name'])
            ->map(fn ($x) => [self::name($x->f_name, $x->l_name) ?: '—', (string) $x->reason, self::date($x->created_at)])->all();
    }

    protected function actions(): array
    {
        return [
            ['key' => 'hide', 'label' => self::L('Hide review', 'إخفاء'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'published',
                'run' => function ($x) {
                    DB::table('place_reviews')->where('id', $x['id'])->update(['is_flagged' => 1, 'updated_at' => now()]);
                    return self::ok('Review hidden');
                }],
            ['key' => 'keep', 'label' => self::L('Keep & clear reports', 'إبقاء ومسح البلاغات'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] !== 'published',
                'run' => function ($x) {
                    DB::transaction(function () use ($x) {
                        DB::table('place_vote_reports')->where('vote_id', $x['id'])->delete();
                        DB::table('place_reviews')->where('id', $x['id'])->update(['is_flagged' => 0, 'updated_at' => now()]);
                    });
                    return self::ok('Published, reports cleared');
                }],
        ];
    }

    public function delete(int|string $id): array
    {
        DB::transaction(function () use ($id) {
            DB::table('place_vote_reports')->where('vote_id', $id)->delete();
            DB::table('place_reviews')->where('id', $id)->delete();
        });
        return self::ok(translate('messages.review_deleted_successfully'));
    }
}
