<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\ConversationController;
use App\Models\Store;
use App\Models\User;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Images;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Support = the admin ↔ customer chats, and (MA-6) admin ↔ store chats from
 * the store app's "Chat with support". A chat is open while the customer or
 * store spoke last, awaiting while support did, and resolved until someone
 * writes after resolved_at.
 */
class TicketsResource extends Resource
{
    public ?string $permission = null;
    protected string $idColumn = 'c.id';
    protected ?string $dateColumn = 'c.last_message_time';
    protected array $search = ['cu.f_name', 'cu.l_name', 'cu.phone', 'lm.message'];
    protected array $sorts = ['id' => 'c.id', 'subject' => 'cu.f_name', 'priority' => "field(c.priority, 'urgent', 'high', 'normal', 'low')", 'created' => 'c.last_message_time'];
    protected string $defaultSort = 'c.last_message_time desc';

    private const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public function config(): array
    {
        return [
            'label' => self::L('Support', 'الدعم'), 'one' => self::L('Ticket', 'تذكرة'),
            'titleKey' => 'subject', 'subKey' => 'customer', 'statusKey' => 'status',
            'modKey' => 'module', 'dateKey' => true, 'reply' => true,
            'editable' => true, 'search' => ['subject', 'customer'],
            'filter' => ['key' => 'status', 'options' => ['open', 'awaiting', 'resolved']],
            'columns' => [
                ['key' => 'id', 'label' => self::L('Ticket', 'التذكرة'), 'w' => '0.7fr', 'strong' => true, 'sort' => 'id'],
                ['key' => 'subject', 'label' => self::L('Last message', 'آخر رسالة'), 'sub' => 'customer', 'w' => '2fr', 'sort' => 'subject'],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'fmt' => 'module', 'w' => '0.8fr', 'allOnly' => true],
                ['key' => 'priorityLabel', 'label' => self::L('Priority', 'الأولوية'), 'w' => '0.7fr', 'sort' => 'priority'],
                ['key' => 'assignee', 'label' => self::L('Assignee', 'المسؤول'), 'w' => '1fr'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '1fr'],
                ['key' => 'created', 'label' => self::L('Updated', 'آخر تحديث'), 'fmt' => 'date', 'w' => '0.7fr', 'sort' => 'created'],
            ],
            'fields' => [
                ['key' => 'customer', 'label' => self::L('Customer', 'العميل'), 'readonly' => true, 'link' => ['customers', 'customer_id']],
                ['key' => 'phone', 'label' => self::L('Phone', 'الهاتف'), 'readonly' => true, 'tel' => true],
                ['key' => 'order', 'label' => self::L('Order', 'الطلب'), 'readonly' => true, 'link' => ['orders', 'order']],
                ['key' => 'merchant', 'label' => self::L('Merchant', 'المتجر'), 'readonly' => true, 'link' => ['merchants', 'merchant_id']],
                ['key' => 'channel', 'label' => self::L('Channel', 'القناة'), 'readonly' => true],
                ['key' => 'priority', 'label' => self::L('Priority', 'الأولوية'), 'type' => 'select', 'show' => 'priorityLabel'],
                ['key' => 'assignee_id', 'label' => self::L('Assignee', 'المسؤول'), 'type' => 'select', 'show' => 'assignee'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
            ],
            'lines' => ['title' => self::L('Conversation', 'المحادثة'), 'thread' => true],
        ];
    }

    /** user_infos.id of the customer or store side of the chat (support is 0 or an admin). */
    private const CUSTOMER_SIDE = "case when c.sender_type = 'admin' or c.sender_id = 0 then c.receiver_id else c.sender_id end";

    protected function statusSql(): string
    {
        return self::cases([
            'resolved' => 'c.resolved_at is not null and c.resolved_at >= c.last_message_time',
            'awaiting' => 'lm.sender_id <> ' . self::CUSTOMER_SIDE,
        ], 'open');
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('conversations as c')
            ->join('user_infos as cu', 'cu.id', '=', DB::raw(self::CUSTOMER_SIDE))
            ->leftJoin('messages as lm', 'lm.id', '=', 'c.last_message_id')
            ->leftJoin('admins as a', 'a.id', '=', 'c.assignee_admin_id')
            ->where(fn ($w) => $w->where('c.sender_type', 'admin')->orWhere('c.receiver_type', 'admin'))
            ->where(fn ($w) => $w->whereIn('c.sender_type', ['customer', 'user', 'vendor'])->orWhereIn('c.receiver_type', ['customer', 'user', 'vendor']))
            ->where(fn ($w) => $w->whereNotNull('cu.user_id')->orWhereNotNull('cu.vendor_id'))
            ->leftJoin(DB::raw('(select vendor_id, min(id) as store_id from stores group by vendor_id) as vs'), 'vs.vendor_id', '=', 'cu.vendor_id')
            ->leftJoin('stores as vst', 'vst.id', '=', 'vs.store_id')
            ->select('c.id', 'c.last_message_time', 'c.priority', 'c.assignee_admin_id', 'c.created_at', 'cu.user_id', 'cu.vendor_id', 'cu.f_name', 'cu.l_name', 'cu.phone',
                'vst.id as store_id', 'vst.name as store_name', 'vst.logo as store_logo', 'vst.module_id as store_module',
                'lm.message as last_message', 'a.f_name as a_f', 'a.l_name as a_l')
            ->selectRaw('(select m.message from messages m where m.conversation_id = c.id and m.sender_id = ' . self::CUSTOMER_SIDE . ' order by m.id desc limit 1) as customer_message')
            ->selectRaw('(select m.order_id from messages m where m.conversation_id = c.id and m.order_id is not null order by m.id desc limit 1) as order_id')
            ->selectRaw('(' . $this->statusSql() . ') as st')
            ->selectRaw('(select image from users where users.id = cu.user_id) as user_image');
        Images::selectDisk($q, User::class, 'cu.user_id', 'image', 'image_disk');
        Images::selectDisk($q, Store::class, 'vst.id', 'logo', 'logo_disk');
        $ids = Scope::ids($mod);
        if ($ids !== null) {
            $q->where(fn ($w) => $w->whereIn('vst.module_id', $ids ?: [0])
                ->orWhereExists(fn ($e) => $e->selectRaw(1)->from('messages as m2')->join('orders as o2', 'o2.id', '=', 'm2.order_id')
                    ->whereColumn('m2.conversation_id', 'c.id')->whereIn('o2.module_id', $ids ?: [0])));
        }
        return $q;
    }

    protected function row(object $r): array
    {
        $order = $r->order_id ? DB::table('orders as o')->leftJoin('stores as s', 's.id', '=', 'o.store_id')
            ->where('o.id', $r->order_id)->first(['o.module_id', 's.name']) : null;
        $subject = trim((string) ($r->customer_message ?: $r->last_message));
        $isStore = !$r->user_id && $r->vendor_id;
        $img = $isStore ? Images::url('store', $r->store_logo, $r->logo_disk) : Images::url('profile', $r->user_image, $r->image_disk);
        return [
            'id' => $r->id,
            'subject' => $subject !== '' ? mb_strimwidth($subject, 0, 80, '…') : 'Attachment',
            'customer' => $isStore ? ($r->store_name ?: self::name($r->f_name, $r->l_name)) : (self::name($r->f_name, $r->l_name) ?: '—'),
            'customer_id' => $r->user_id,
            'vendor_id' => $r->vendor_id,
            'isStore' => (bool) $isStore,
            'img' => $img,
            'avatars' => ['subject' => $img],
            'phone' => $r->phone,
            'order' => $r->order_id,
            'merchant' => $isStore ? $r->store_name : ($order->name ?? null),
            'merchant_id' => $isStore ? $r->store_id : null,
            'module' => $isStore ? Scope::code($r->store_module) : ($order ? Scope::code($order->module_id) : 'all'),
            'channel' => $isStore ? 'Store app chat' : 'In-app chat',
            'priority' => $r->priority ?: 'normal',
            'priorityLabel' => self::PRIORITIES[$r->priority] ?? 'Normal',
            'assignee_id' => $r->assignee_admin_id,
            'assignee' => self::name($r->a_f, $r->a_l) ?: 'Unassigned',
            'status' => $r->st,
            'created' => self::date($r->last_message_time ?? $r->created_at),
        ];
    }

    protected function lines(array $row): array
    {
        $customerInfo = $row['isStore']
            ? DB::table('user_infos')->where('vendor_id', $row['vendor_id'])->value('id')
            : DB::table('user_infos')->where('user_id', $row['customer_id'])->value('id');
        return DB::table('messages')->where('conversation_id', $row['id'])->orderBy('id')->limit(200)->get()
            ->map(fn ($m) => [
                $m->sender_id == $customerInfo ? $row['customer'] : 'Waddy Support',
                (string) ($m->message ?? '') !== '' ? $m->message : '📎',
                $m->created_at ? date('d M H:i', strtotime($m->created_at)) : '',
                $m->sender_id != $customerInfo,
            ])->all();
    }

    public function options(?array $row = null): array
    {
        return [
            'priority' => collect(self::PRIORITIES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all(),
            'assignee_id' => DB::table('admins')->orderBy('f_name')->get(['id', 'f_name', 'l_name'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => self::name($a->f_name, $a->l_name)])
                ->prepend(['value' => '', 'label' => 'Unassigned'])->all(),
        ];
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.message.list');
    }

    protected function actions(): array
    {
        return [
            ['key' => 'resolve', 'label' => self::L('Mark resolved', 'تم الحل'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] !== 'resolved',
                'run' => function ($x) {
                    DB::table('conversations')->where('id', $x['id'])->update(['resolved_at' => now()]);
                    return self::ok('Ticket resolved');
                }],
            ['key' => 'reopen', 'label' => self::L('Reopen', 'إعادة فتح'), 'tone' => 'neutral',
                'when' => fn ($x) => $x['status'] === 'resolved',
                'run' => function ($x) {
                    DB::table('conversations')->where('id', $x['id'])->update(['resolved_at' => null]);
                    return self::ok('Ticket reopened');
                }],
        ];
    }

    /** Sends a reply as the admin, through the classic chat (push included). */
    public function reply(int $id, string $text): array
    {
        $row = $this->find($id);
        if (!$row) {
            return self::fail(translate('messages.not_found'));
        }
        $result = $row['isStore']
            ? ClassicAction::run(ConversationController::class, 'storeVendor', ['reply' => $text], ['vendor_id' => $row['vendor_id']])
            : ClassicAction::run(ConversationController::class, 'store', ['reply' => $text], ['user_id' => $row['customer_id']]);
        return $result['ok'] ? self::ok('Reply sent') : $result;
    }

    public function update(int|string $id, array $data): array
    {
        $v = Validator::make($data, [
            'priority' => 'required|in:' . implode(',', array_keys(self::PRIORITIES)),
            'assignee_id' => 'nullable|exists:admins,id',
        ]);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        DB::table('conversations')->where('id', $id)->update([
            'priority' => $data['priority'],
            'assignee_admin_id' => $data['assignee_id'] ?: null,
        ]);
        return self::ok(translate('messages.updated_successfully'));
    }
}
