<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\ItemController;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Item reviews from orders. Order reviews have no report flow, only visibility. */
class ReviewsResource extends Resource
{
    public ?string $permission = 'customer_management';
    protected string $idColumn = 'r.id';
    protected ?string $merchantColumn = 's.id';
    protected ?string $merchantNameSql = 's.name';
    protected ?string $dateColumn = 'r.created_at';
    protected array $search = ['s.name', 'i.name', 'u.f_name', 'u.l_name', 'r.comment'];
    protected array $sorts = ['merchant' => 's.name', 'module' => 'r.module_id', 'rating' => 'r.rating', 'date' => 'r.created_at'];

    public function config(): array
    {
        return [
            'label' => self::L('Reviews', 'التقييمات'), 'one' => self::L('Review', 'تقييم'),
            'titleKey' => 'merchant', 'subKey' => 'customer', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'merchantKey' => true, 'square' => true,
            'editable' => true, 'search' => ['merchant', 'customer', 'comment'],
            'filter' => ['key' => 'status', 'options' => ['published', 'hidden']],
            'columns' => [
                ['key' => 'merchant', 'label' => self::L('Merchant', 'المتجر'), 'sub' => 'customer', 'w' => '1.4fr', 'strong' => true, 'sort' => 'merchant'],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'fmt' => 'module', 'w' => '0.8fr', 'allOnly' => true, 'sort' => 'module'],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'stars', 'w' => '0.8fr', 'sort' => 'rating'],
                ['key' => 'comment', 'label' => self::L('Comment', 'التعليق'), 'w' => '2fr', 'muted' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.9fr'],
                ['key' => 'date', 'label' => self::L('Date', 'التاريخ'), 'fmt' => 'date', 'w' => '0.7fr', 'sort' => 'date'],
            ],
            'fields' => [
                ['key' => 'merchant', 'label' => self::L('Merchant', 'المتجر'), 'readonly' => true],
                ['key' => 'customer', 'label' => self::L('Customer', 'العميل'), 'readonly' => true],
                ['key' => 'item', 'label' => self::L('Item', 'المنتج'), 'readonly' => true],
                ['key' => 'order', 'label' => self::L('Order', 'الطلب'), 'readonly' => true],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'stars', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
                ['key' => 'comment', 'label' => self::L('Comment', 'التعليق'), 'readonly' => true, 'wide' => true],
                ['key' => 'reply', 'label' => self::L('Public reply', 'رد علني'), 'type' => 'textarea', 'wide' => true],
            ],
        ];
    }

    protected function statusSql(): string
    {
        return self::cases(['hidden' => 'r.status = 0'], 'published');
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('reviews as r')
            ->leftJoin('items as i', 'i.id', '=', 'r.item_id')
            ->join('stores as s', 's.id', '=', DB::raw('coalesce(r.store_id, i.store_id)'))
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->select('r.id', 'r.rating', 'r.comment', 'r.reply', 'r.order_id', 'r.created_at', 'r.module_id', 's.module_id as store_module',
                's.name as store_name', 'i.name as item_name', 'u.f_name', 'u.l_name')
            ->selectRaw('(' . $this->statusSql() . ') as st');
        if ($zone = Scope::adminZone()) {
            $q->where('s.zone_id', $zone);
        }
        return self::module($q, $mod, 's.module_id');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'merchant' => $r->store_name,
            'customer' => self::name($r->f_name, $r->l_name) ?: '—',
            'item' => $r->item_name,
            'module' => Scope::code($r->module_id ?? $r->store_module),
            'order' => $r->order_id,
            'rating' => (int) $r->rating,
            'comment' => (string) $r->comment,
            'reply' => (string) $r->reply,
            'status' => $r->st,
            'date' => self::date($r->created_at),
        ];
    }

    protected function actions(): array
    {
        $status = fn ($id, $s) => ClassicAction::run(ItemController::class, 'reviews_status', ['id' => $id, 'status' => $s], [], 'GET');
        return [
            ['key' => 'hide', 'label' => self::L('Hide review', 'إخفاء التقييم'), 'tone' => 'danger',
                'when' => fn ($x) => $x['status'] === 'published', 'run' => fn ($x) => $status($x['id'], 0)],
            ['key' => 'publish', 'label' => self::L('Publish', 'نشر'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'hidden', 'run' => fn ($x) => $status($x['id'], 1)],
        ];
    }

    public function update(int|string $id, array $data): array
    {
        $v = Validator::make($data, ['reply' => 'nullable|string|max:2000']);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        $reply = trim((string) ($data['reply'] ?? ''));
        DB::table('reviews')->where('id', $id)->update([
            'reply' => $reply ?: null,
            'replied_at' => $reply ? now() : null,
            'updated_at' => now(),
        ]);
        return self::ok(translate('messages.review_reply_updated'));
    }
}
