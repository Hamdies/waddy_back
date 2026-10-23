<?php

namespace App\Services\AdminPanel;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One list page of the admin panel (orders, merchants, places…).
 *
 * A resource describes its page to the browser (`config`) and answers the
 * list, detail and action calls. The list pipeline is shared: every resource
 * gives a base query plus the SQL for its status, zone, merchant, date and
 * search columns, and this class does the filtering, facet counts, sorting
 * and paging the design's toolbar needs.
 */
abstract class Resource
{
    public const PAGE_SIZE = 12;

    /** Classic permission key (Helpers::module_permission_check), null for any admin. */
    public ?string $permission = null;

    protected string $idColumn = 'id';
    protected ?string $zoneColumn = null;
    protected string $zoneTable = 'zones';
    protected ?string $merchantColumn = null;
    protected ?string $merchantNameSql = null;
    protected ?string $dateColumn = null;
    /** @var string[] SQL columns matched by the search box */
    protected array $search = [];
    /** @var array<string,string> column key => SQL to order by */
    protected array $sorts = [];
    protected string $defaultSort = '';

    /** Module the request is looking at; most details ignore it, fee and count fields don't. */
    protected string $mod = 'all';

    /** What the browser needs to draw the page: labels, columns, fields, statuses. */
    abstract public function config(): array;

    /** Base query with joins and selects, scoped to the module and the admin's zone. */
    abstract protected function query(string $mod): Builder;

    /** SQL expression giving each row's status code. */
    abstract protected function statusSql(): string;

    /** Turns a query row into the keys the page's columns and fields read. */
    abstract protected function row(object $r): array;

    public function withModule(string $mod): static
    {
        $this->mod = $mod;
        return $this;
    }

    // ---------------------------------------------------------------- list

    public function list(Request $request, string $mod): array
    {
        $base = $this->query($mod);
        $pre = $this->filter(clone $base, $request, false);
        $filtered = $this->filter(clone $base, $request, true);

        $status = $this->statusSql();
        $counts = $this->bare($pre)->selectRaw("($status) as st, count(*) as c")->groupBy("st")->pluck('c', 'st');

        $total = $this->bare($filtered)->count();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(0, (int) $request->query('page', 0)), $pages - 1);

        $rows = $this->sorted($filtered, $request)
            ->offset($page * self::PAGE_SIZE)->limit(self::PAGE_SIZE)->get();

        return [
            'rows' => $this->rows($rows),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'counts' => $counts,
            'all' => array_sum($counts->all()),
            'zones' => $this->zoneFacet($base),
            'merchants' => $this->merchantFacet($base),
        ];
    }

    /** Every filtered row (sorted) for CSV export. */
    public function export(Request $request, string $mod): array
    {
        $q = $this->filter($this->query($mod), $request, true);
        return $this->rows($this->sorted($q, $request)->limit(5000)->get());
    }

    /** Matches for the ⌘K palette. */
    public function lookup(string $term, string $mod, int $limit = 4): array
    {
        $q = $this->query($mod);
        $this->applySearch($q, $term);
        return $this->rows($this->sorted($q, new Request())->limit($limit)->get());
    }

    /** Newest rows, for the home page. */
    public function recent(string $mod, int $limit): array
    {
        return $this->rows($this->sorted($this->query($mod), new Request())->limit($limit)->get());
    }

    /** Status counts for nav badges and the home page. */
    public function statusCount(string $mod, string $code): int
    {
        $status = $this->statusSql();
        return $this->bare($this->query($mod))->whereRaw("($status) = ?", [$code])->count();
    }

    protected function rows($collection): array
    {
        return $collection->map(fn ($r) => $this->row($r))->values()->all();
    }

    protected function filter(Builder $q, Request $request, bool $withStatus): Builder
    {
        $statuses = array_filter((array) $request->query('status', []));
        if ($withStatus && $statuses) {
            $marks = implode(',', array_fill(0, count($statuses), '?'));
            $q->whereRaw('(' . $this->statusSql() . ") in ($marks)", array_values($statuses));
        }

        $zones = array_filter((array) $request->query('zones', []));
        if ($this->zoneColumn && $zones) {
            $q->whereIn($this->zoneColumn, $zones);
        }

        if ($this->merchantColumn && $request->filled('merchant')) {
            $q->where($this->merchantColumn, $request->query('merchant'));
        }

        if ($this->dateColumn && ($range = $this->dateRange($request))) {
            $q->whereBetween($this->dateColumn, $range);
        }

        if ($request->filled('q')) {
            $this->applySearch($q, trim($request->query('q')));
        }

        return $q;
    }

    protected function applySearch(Builder $q, string $term): void
    {
        $id = $this->idColumn;
        $q->where(function ($w) use ($term, $id) {
            if (ctype_digit($term)) {
                $w->orWhere($id, $term);
            }
            foreach ($this->search as $col) {
                $w->orWhere(DB::raw($col), 'like', '%' . $term . '%');
            }
        });
    }

    protected function dateRange(Request $request): ?array
    {
        $now = now();
        return match ($request->query('date')) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : Carbon::create(2000),
                $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : $now->copy()->addYears(5),
            ],
            default => null,
        };
    }

    protected function sorted(Builder $q, Request $request): Builder
    {
        $key = $request->query('sort');
        if ($key && isset($this->sorts[$key])) {
            $dir = (int) $request->query('dir', 1) > 0 ? 'asc' : 'desc';
            return $q->orderByRaw($this->sorts[$key] . ' ' . $dir)->orderBy($this->idColumn, 'desc');
        }
        return $this->defaultSort ? $q->orderByRaw($this->defaultSort) : $q->orderBy($this->idColumn, 'desc');
    }

    /** The query without its selects and ordering, for counts. */
    protected function bare(Builder $q): Builder
    {
        return (clone $q)->cloneWithout(['columns', 'orders', 'limit', 'offset'])->cloneWithoutBindings(['select', 'order']);
    }

    protected function zoneFacet(Builder $base): array
    {
        if (!$this->zoneColumn) {
            return [];
        }
        $counts = $this->bare($base)->selectRaw("{$this->zoneColumn} as zid, count(*) as c")->groupBy($this->zoneColumn)->pluck('c', 'zid');
        return DB::table($this->zoneTable)
            ->when(Scope::adminZone() && $this->zoneTable === 'zones', fn ($q) => $q->where('id', Scope::adminZone()))
            ->orderBy('id')->get(['id', DB::raw('coalesce(display_name, name) as name')])
            ->map(fn ($z) => ['id' => $z->id, 'name' => $z->name, 'count' => (int) ($counts[$z->id] ?? 0)])
            ->all();
    }

    protected function merchantFacet(Builder $base): array
    {
        if (!$this->merchantColumn) {
            return [];
        }
        return $this->bare($base)
            ->selectRaw("{$this->merchantColumn} as mid, {$this->merchantNameSql} as name, count(*) as c")
            ->whereNotNull($this->merchantColumn)
            ->groupByRaw("{$this->merchantColumn}, {$this->merchantNameSql}")
            ->orderByRaw($this->merchantNameSql)
            ->limit(300)->get()
            ->map(fn ($m) => ['id' => $m->mid, 'name' => (string) $m->name, 'count' => (int) $m->c])
            ->all();
    }

    // -------------------------------------------------------------- detail

    public function find(int|string $id): ?array
    {
        $r = $this->query('all')->where($this->idColumn, $id)->first();
        return $r ? $this->row($r) : null;
    }

    /** Detail payload for the side panel. */
    public function show(int|string $id): ?array
    {
        $row = $this->find($id);
        if (!$row) {
            return null;
        }
        return [
            'row' => $row,
            'lines' => $this->lines($row),
            'actions' => array_values(array_map(
                fn ($a) => ['key' => $a['key'], 'label' => $a['label'], 'tone' => $a['tone']],
                array_filter($this->actions(), fn ($a) => ($a['when'])($row))
            )),
            'options' => $this->options($row),
            'classicUrl' => $this->classicUrl($row),
        ];
    }

    /** Extra rows under the fields: items, a conversation, permissions… */
    protected function lines(array $row): array
    {
        return [];
    }

    /** Options for select fields, keyed by field key. */
    public function options(?array $row = null): array
    {
        return [];
    }

    /** Link to the same record in the classic admin, when it has a page. */
    protected function classicUrl(array $row): ?string
    {
        return null;
    }

    // ------------------------------------------------------------- actions

    /**
     * Row actions: key, label [en, ar], tone (primary|neutral|danger),
     * `when` (row => bool) and `run` (row => [ok, message]).
     */
    protected function actions(): array
    {
        return [];
    }

    public function act(int|string $id, string $key, Request $request): array
    {
        $row = $this->find($id);
        $action = collect($this->actions())->firstWhere('key', $key);
        if (!$row || !$action || !($action['when'])($row)) {
            return ['ok' => false, 'message' => translate('messages.not_found')];
        }
        return ($action['run'])($row, $request);
    }

    public function blank(string $mod): ?array
    {
        return null;
    }

    public function create(array $data, string $mod): array
    {
        return ['ok' => false, 'message' => translate('messages.not_found')];
    }

    public function update(int|string $id, array $data): array
    {
        return ['ok' => false, 'message' => translate('messages.not_found')];
    }

    public function delete(int|string $id): array
    {
        return ['ok' => false, 'message' => translate('messages.not_found')];
    }

    // ------------------------------------------------------------- helpers

    protected static function date($value): string
    {
        return $value ? Carbon::parse($value)->toDateString() : '';
    }

    protected static function name(?string $first, ?string $last): string
    {
        return trim(($first ?? '') . ' ' . ($last ?? ''));
    }

    protected static function module(Builder $q, string $mod, string $column): Builder
    {
        $ids = Scope::ids($mod);
        return $ids === null ? $q : $q->whereIn($column, $ids ?: [0]);
    }

    /** CASE expression from [code => SQL condition], checked in order. */
    protected static function cases(array $map, string $else): string
    {
        $sql = 'CASE';
        foreach ($map as $code => $cond) {
            $sql .= " WHEN $cond THEN '$code'";
        }
        return $sql . " ELSE '$else' END";
    }

    protected static function ok(string $message): array
    {
        return ['ok' => true, 'message' => $message];
    }

    protected static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }

    /** Keeps the English translation row (what the apps show) in step with an edited column. */
    protected static function syncTranslation(string $model, int|string $id, string $key, ?string $value): void
    {
        DB::table('translations')
            ->where(['translationable_type' => $model, 'translationable_id' => $id, 'key' => $key, 'locale' => 'en'])
            ->update(['value' => $value, 'updated_at' => now()]);
    }

    /** [en, ar] label pair, the format the browser config uses. */
    protected static function L(string $en, string $ar): array
    {
        return [$en, $ar];
    }
}
