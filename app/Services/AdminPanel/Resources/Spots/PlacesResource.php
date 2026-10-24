<?php

namespace App\Services\AdminPanel\Resources\Spots;

use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Images;
use App\Services\AdminPanel\Resource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceTranslation;
use Modules\PlacesToVisit\Entities\Scopes\SurfaceScope;
use Modules\PlacesToVisit\Http\Controllers\Admin\PlaceController;
use Modules\PlacesToVisit\Services\LeaderboardService;
use Modules\PlacesToVisit\Services\RaceClock;

/** Spots in the weekly race. Votes this week count only unflagged votes, like the leaderboard. */
class PlacesResource extends Resource
{
    use SpotsQueries;

    protected string $idColumn = 'p.id';
    protected ?string $zoneColumn = 'p.zone_id';
    protected string $zoneTable = 'place_zones';
    protected ?string $dateColumn = 'p.created_at';
    protected array $search = ['pt.title', 'pc.name', 'pz.name'];
    protected array $sorts = ['name' => 'pt.title', 'zone' => 'pz.name', 'votesWeek' => 'votes_week', 'rating' => 'rating', 'crowns' => 'crowns', 'prizeCap' => 'p.prize_value_cap'];

    public function config(): array
    {
        return [
            'label' => self::L('Places', 'الأماكن'), 'one' => self::L('Place', 'مكان'),
            'titleKey' => 'name', 'subKey' => 'category', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'zoneKey' => true, 'square' => true, 'photo' => true,
            'editable' => true, 'newUrl' => route('admin.places.create'), 'search' => ['name', 'category', 'zone'],
            'filter' => ['key' => 'status', 'options' => ['active', 'hidden']],
            'columns' => [
                ['key' => 'name', 'label' => self::L('Place', 'المكان'), 'sub' => 'category', 'w' => '1.7fr', 'strong' => true, 'sort' => 'name'],
                ['key' => 'zone', 'label' => self::L('Zone', 'المنطقة'), 'w' => '1fr', 'sort' => 'zone'],
                ['key' => 'votesWeek', 'label' => self::L('Votes this week', 'أصوات الأسبوع'), 'fmt' => 'int', 'w' => '0.9fr', 'sort' => 'votesWeek'],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'rating', 'w' => '0.7fr', 'sort' => 'rating'],
                ['key' => 'crowns', 'label' => self::L('Crowns', 'التتويجات'), 'fmt' => 'int', 'w' => '0.6fr', 'sort' => 'crowns'],
                ['key' => 'prizeCap', 'label' => self::L('Voucher cap', 'حد القسيمة'), 'fmt' => 'egp', 'w' => '0.8fr', 'sort' => 'prizeCap'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.8fr'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => self::L('Name', 'الاسم'), 'required' => true],
                ['key' => 'category_id', 'label' => self::L('Category', 'الفئة'), 'type' => 'select', 'show' => 'category'],
                ['key' => 'zone_id', 'label' => self::L('Zone', 'المنطقة'), 'type' => 'select', 'show' => 'zone'],
                ['key' => 'prizeCap', 'label' => self::L('Voucher cap (EGP, empty = default)', 'حد القسيمة (ج.م)'), 'type' => 'number', 'fmt' => 'egp'],
                ['key' => 'votesWeek', 'label' => self::L('Votes this week', 'أصوات الأسبوع'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'rating', 'label' => self::L('Avg. review rating', 'متوسط التقييم'), 'fmt' => 'rating', 'readonly' => true],
                ['key' => 'reviews', 'label' => self::L('Reviews', 'التقييمات'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'crowns', 'label' => self::L('Weeks crowned', 'مرات التتويج'), 'fmt' => 'int', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
                ['key' => 'redeemToken', 'label' => self::L('Redeem token', 'رمز الاستخدام'), 'readonly' => true, 'wide' => true],
            ],
            'lines' => ['title' => self::L('Counter page', 'صفحة الكاشير')],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['hidden' => 'p.is_active = 0'], 'active');
    }

    protected function query(string $mod): Builder
    {
        return $this->placeDetails(DB::table('places as p'))->select('p.id', 'p.image', 'p.cover_image', 'p.category_id', 'p.zone_id', 'p.prize_value_cap', 'p.redeem_token', 'p.created_at',
                'pt.title', 'pc.name as category_name', 'pz.name as zone_name')
            ->selectRaw('(select count(*) from place_votes v where v.place_id = p.id and v.period = ? and v.is_flagged = 0) as votes_week', [RaceClock::period()])
            ->selectRaw('(select avg(rating) from place_reviews r where r.place_id = p.id and r.is_flagged = 0) as rating')
            ->selectRaw('(select count(*) from place_reviews r where r.place_id = p.id) as reviews')
            ->selectRaw('(select count(*) from place_winners w where w.place_id = p.id and w.zone_id is null) as crowns')
            ->selectRaw('(' . $this->statusSql() . ') as st');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->title ?: ('#' . $r->id),
            'img' => $img = Images::url('places', $r->image ?: $r->cover_image),
            'avatars' => ['name' => $img],
            'module' => 'spots',
            'category' => $r->category_name,
            'category_id' => $r->category_id,
            'zone' => $r->zone_name,
            'zone_id' => $r->zone_id,
            'votesWeek' => (int) $r->votes_week,
            'rating' => $r->rating ? number_format($r->rating, 1) : '—',
            'reviews' => (int) $r->reviews,
            'crowns' => (int) $r->crowns,
            'prizeCap' => $r->prize_value_cap === null ? (float) config('placestovisit.prize.value_cap', 60) : (float) $r->prize_value_cap,
            'redeemToken' => $r->redeem_token,
            'status' => $r->st,
        ];
    }

    protected function lines(array $row): array
    {
        if (!$row['redeemToken']) {
            return [];
        }
        $url = url('spots/redeem/' . $row['redeemToken']);
        return [[$url, 'EN', ''], [$url . '?lang=ar', 'AR', '']];
    }

    public function options(?array $row = null): array
    {
        return [
            'category_id' => DB::table('place_categories')->where('surface', SurfaceScope::SPOTS)->orderBy('priority')->orderBy('name')
                ->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'zone_id' => DB::table('place_zones')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($z) => ['value' => $z->id, 'label' => $z->name])->prepend(['value' => '', 'label' => '—'])->all(),
        ];
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.places.edit', ['place' => $row['id']]);
    }

    protected function actions(): array
    {
        $run = fn ($method, $id) => ClassicAction::run(PlaceController::class, $method, [], ['place' => Place::withoutGlobalScopes()->find($id)], 'GET');
        $toggle = function ($x) use ($run) {
            $result = $run('toggleStatus', $x['id']);
            app(LeaderboardService::class)->clearCache();
            return $result;
        };
        return [
            ['key' => 'token', 'label' => self::L('Regenerate redeem token', 'رمز كاشير جديد'), 'tone' => 'neutral',
                'when' => fn () => true,
                'run' => function ($x) use ($run) {
                    $result = $run('regenerateRedeemToken', $x['id']);
                    return $result['ok'] ? self::ok('New counter link issued. The old link stops working.') : $result;
                }],
            ['key' => 'hide', 'label' => self::L('Hide from race', 'إخفاء من السباق'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'active', 'run' => $toggle],
            ['key' => 'show', 'label' => self::L('Show in race', 'إظهار في السباق'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'hidden', 'run' => $toggle],
        ];
    }

    public function update(int|string $id, array $data): array
    {
        $v = Validator::make($data, [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:place_categories,id',
            'zone_id' => 'nullable|exists:place_zones,id',
            'prizeCap' => 'nullable|numeric|min:0|max:100000',
        ]);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        $default = (float) config('placestovisit.prize.value_cap', 60);
        $cap = ($data['prizeCap'] ?? '') === '' || (float) $data['prizeCap'] === $default ? null : $data['prizeCap'];
        DB::table('places')->where('id', $id)->update([
            'category_id' => $data['category_id'],
            'zone_id' => $data['zone_id'] ?: null,
            'prize_value_cap' => $cap,
            'updated_at' => now(),
        ]);
        PlaceTranslation::updateOrCreate(['place_id' => $id, 'locale' => 'en'], ['title' => $data['name']]);
        app(LeaderboardService::class)->clearCache();
        return self::ok(translate('messages.place_updated_successfully'));
    }
}
