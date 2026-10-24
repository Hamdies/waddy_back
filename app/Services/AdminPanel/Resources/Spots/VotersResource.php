<?php

namespace App\Services\AdminPanel\Resources\Spots;

use App\Models\User;
use App\Services\AdminPanel\Images;
use App\Services\AdminPanel\Resource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\PlacesToVisit\Services\RaceClock;

/** Everyone who has voted, ranked by weeks voted. Odds in the draw are the same for every voter. */
class VotersResource extends Resource
{
    protected string $idColumn = 'u.id';
    protected array $search = ['u.f_name', 'u.l_name', 'u.phone'];
    protected array $sorts = ['name' => 'u.f_name', 'weeks' => 'weeks', 'xp' => 'xp', 'prizesWon' => 'prizes_won', 'lastVote' => 'last_vote'];
    protected string $defaultSort = 'weeks desc';

    public function config(): array
    {
        return [
            'label' => self::L('Top voters', 'أكثر المصوتين'), 'one' => self::L('Voter', 'مصوت'),
            'titleKey' => 'name', 'subKey' => 'phone', 'statusKey' => 'status',
            'modKey' => 'module', 'search' => ['name'],
            'filter' => ['key' => 'status', 'options' => ['active', 'blocked']],
            'columns' => [
                ['key' => 'name', 'label' => self::L('Voter', 'المصوت'), 'sub' => 'phone', 'w' => '1.7fr', 'strong' => true, 'sort' => 'name'],
                ['key' => 'weeks', 'label' => self::L('Weeks voted', 'أسابيع التصويت'), 'fmt' => 'int', 'w' => '0.8fr', 'sort' => 'weeks'],
                ['key' => 'streak', 'label' => self::L('Streak', 'متتالية'), 'fmt' => 'int', 'w' => '0.6fr'],
                ['key' => 'xp', 'label' => self::L('Spots XP', 'نقاط الأماكن'), 'fmt' => 'int', 'w' => '0.6fr', 'sort' => 'xp'],
                ['key' => 'prizesWon', 'label' => self::L('Vouchers won', 'قسائم مكتسبة'), 'fmt' => 'int', 'w' => '0.8fr', 'sort' => 'prizesWon'],
                ['key' => 'lastVote', 'label' => self::L('Last vote', 'آخر تصويت'), 'w' => '0.8fr', 'muted' => true, 'sort' => 'lastVote'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.8fr'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => self::L('Voter', 'المصوت'), 'readonly' => true],
                ['key' => 'phone', 'label' => self::L('Phone', 'الهاتف'), 'readonly' => true],
                ['key' => 'weeks', 'label' => self::L('Weeks voted (all time)', 'أسابيع التصويت'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'streak', 'label' => self::L('Current streak', 'المتتالية الحالية'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'xp', 'label' => self::L('Spots XP', 'نقاط الأماكن'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'prizesWon', 'label' => self::L('Vouchers won', 'قسائم مكتسبة'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'lastVote', 'label' => self::L('Last vote', 'آخر تصويت'), 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Account', 'الحساب'), 'readonly' => true, 'status' => true],
            ],
            'lines' => ['title' => self::L('Draw odds', 'فرص السحب')],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['blocked' => 'u.status = 0'], 'active');
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('users as u')
            ->whereExists(fn ($e) => $e->selectRaw(1)->from('place_votes as v')->whereColumn('v.user_id', 'u.id'))
            ->select('u.id', 'u.f_name', 'u.l_name', 'u.phone', 'u.image')
            ->selectRaw('(select count(distinct v.period) from place_votes v where v.user_id = u.id) as weeks')
            ->selectRaw('(select max(v.period) from place_votes v where v.user_id = u.id) as last_vote')
            ->selectRaw('(select count(*) from place_prizes z where z.user_id = u.id) as prizes_won')
            ->selectRaw("(select coalesce(sum(x.xp_amount), 0) from xp_transactions x where x.user_id = u.id and x.is_reversed = 0 and x.xp_source like 'place%') as xp")
            ->selectRaw('(' . $this->statusSql() . ') as st');
        return Images::selectDisk($q, User::class, 'u.id', 'image', 'image_disk');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'name' => self::name($r->f_name, $r->l_name) ?: '—',
            'img' => $img = Images::url('profile', $r->image, $r->image_disk),
            'avatars' => ['name' => $img],
            'phone' => $r->phone,
            'module' => 'spots',
            'weeks' => (int) $r->weeks,
            'streak' => self::streak($r->id),
            'xp' => (int) $r->xp,
            'prizesWon' => (int) $r->prizes_won,
            'lastVote' => $r->last_vote,
            'status' => $r->st,
        ];
    }

    /** Consecutive weeks voted, counting back from this week (or last week if this one has no vote yet). */
    private static function streak(int $userId): int
    {
        $periods = DB::table('place_votes')->where('user_id', $userId)->distinct()->pluck('period')->flip();
        $weeksAgo = $periods->has(RaceClock::period()) ? 0 : 1;
        $streak = 0;
        while ($periods->has(RaceClock::periodWeeksAgo($weeksAgo + $streak))) {
            $streak++;
        }
        return $streak;
    }

    protected function lines(array $row): array
    {
        return [['Same chance as every other voter in the pool', 'uniform', '']];
    }
}
