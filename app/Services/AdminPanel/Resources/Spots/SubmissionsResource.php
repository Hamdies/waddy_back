<?php

namespace App\Services\AdminPanel\Resources\Spots;

use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Resource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\PlacesToVisit\Entities\PlaceSubmission;
use Modules\PlacesToVisit\Entities\Scopes\SurfaceScope;
use Modules\PlacesToVisit\Http\Controllers\Admin\PlaceSubmissionController;

/** Hidden-gem suggestions from the app. Approving creates the place and pays the submitter's XP. */
class SubmissionsResource extends Resource
{
    use SpotsQueries;

    protected string $idColumn = 's.id';
    protected ?string $dateColumn = 's.created_at';
    protected array $search = ['s.title', 's.address', 'u.f_name', 'u.l_name'];
    protected array $sorts = ['name' => 's.title', 'submitter' => 'u.f_name', 'submitted' => 's.created_at'];

    public function config(): array
    {
        $xp = (int) config('placestovisit.xp.submission_approved', 25);
        return [
            'label' => self::L('Hidden-gem submissions', 'اقتراحات الأماكن'), 'one' => self::L('Submission', 'اقتراح'),
            'titleKey' => 'name', 'subKey' => 'submitter', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'square' => true,
            'editable' => true, 'deletable' => true, 'search' => ['name', 'submitter'],
            'filter' => ['key' => 'status', 'options' => ['pending', 'approved', 'rejected']],
            'xp' => $xp,
            'columns' => [
                ['key' => 'name', 'label' => self::L('Place', 'المكان'), 'sub' => 'category', 'w' => '1.7fr', 'strong' => true, 'sort' => 'name'],
                ['key' => 'submitter', 'label' => self::L('Submitted by', 'مقدم الاقتراح'), 'w' => '1.3fr', 'sort' => 'submitter'],
                ['key' => 'address', 'label' => self::L('Address', 'العنوان'), 'w' => '1.2fr', 'muted' => true],
                ['key' => 'submitted', 'label' => self::L('Submitted', 'التاريخ'), 'fmt' => 'date', 'w' => '0.8fr', 'sort' => 'submitted'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.9fr'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => self::L('Place', 'المكان'), 'required' => true],
                ['key' => 'category_id', 'label' => self::L('Category', 'الفئة'), 'type' => 'select', 'show' => 'category'],
                ['key' => 'submitter', 'label' => self::L('Submitted by', 'مقدم الاقتراح'), 'readonly' => true],
                ['key' => 'submitted', 'label' => self::L('Submitted', 'التاريخ'), 'fmt' => 'date', 'readonly' => true],
                ['key' => 'address', 'label' => self::L('Address', 'العنوان'), 'readonly' => true, 'wide' => true],
                ['key' => 'location', 'label' => self::L('Pinned on map', 'محدد على الخريطة'), 'fmt' => 'bool', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
                ['key' => 'description', 'label' => self::L('Why it is a gem', 'الوصف'), 'readonly' => true, 'wide' => true],
                ['key' => 'note', 'label' => self::L('Note to submitter', 'ملاحظة'), 'type' => 'textarea', 'wide' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['approved' => "s.status = 'approved'", 'rejected' => "s.status = 'rejected'"], 'pending');
    }

    protected function query(string $mod): Builder
    {
        return DB::table('place_submissions as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('place_categories as pc', 'pc.id', '=', 's.category_id')
            ->select('s.id', 's.title', 's.description', 's.address', 's.latitude', 's.longitude', 's.category_id', 's.admin_note', 's.created_at',
                'pc.name as category_name', 'u.f_name', 'u.l_name')
            ->selectRaw('(' . $this->statusSql() . ') as st');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->title,
            'module' => 'spots',
            'category' => $r->category_name ?: '—',
            'category_id' => $r->category_id,
            'submitter' => self::name($r->f_name, $r->l_name) ?: '—',
            'address' => (string) $r->address,
            'location' => $r->latitude !== null && $r->longitude !== null,
            'description' => (string) $r->description,
            'note' => (string) $r->admin_note,
            'submitted' => self::date($r->created_at),
            'status' => $r->st,
        ];
    }

    public function options(?array $row = null): array
    {
        return ['category_id' => DB::table('place_categories')->where('surface', SurfaceScope::SPOTS)->orderBy('name')
            ->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->prepend(['value' => '', 'label' => '—'])->all()];
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.places.submissions.show', ['submission' => $row['id']]);
    }

    protected function actions(): array
    {
        $xp = (int) config('placestovisit.xp.submission_approved', 25);
        return [
            ['key' => 'approve', 'label' => self::L("Approve (+$xp XP)", "قبول (+$xp نقطة)"), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'pending',
                'run' => function ($x) use ($xp) {
                    if (!$x['category_id']) {
                        return self::fail('Pick a category first (Edit).');
                    }
                    if (!$x['location']) {
                        return self::fail('This submission has no map pin. Approve it in the classic admin, where you can drop one.');
                    }
                    $result = ClassicAction::run(PlaceSubmissionController::class, 'approve',
                        ['category_id' => $x['category_id'], 'admin_note' => $x['note'] ?: null], ['submission' => PlaceSubmission::find($x['id'])]);
                    return $result['ok'] ? self::ok("Approved. $xp XP sent to the submitter.") : $result;
                }],
            ['key' => 'reject', 'label' => self::L('Reject', 'رفض'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'pending',
                'run' => fn ($x) => ClassicAction::run(PlaceSubmissionController::class, 'reject',
                    ['admin_note' => $x['note'] ?: null], ['submission' => PlaceSubmission::find($x['id'])])],
        ];
    }

    public function update(int|string $id, array $data): array
    {
        $v = Validator::make($data, [
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:place_categories,id',
            'note' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        DB::table('place_submissions')->where('id', $id)->update([
            'title' => $data['name'],
            'category_id' => $data['category_id'] ?: null,
            'admin_note' => $data['note'] ?? null,
            'updated_at' => now(),
        ]);
        return self::ok(translate('messages.updated_successfully'));
    }

    public function delete(int|string $id): array
    {
        return ClassicAction::run(PlaceSubmissionController::class, 'destroy', [], ['submission' => PlaceSubmission::find($id)], 'DELETE');
    }
}
