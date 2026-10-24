<?php

namespace App\Services\AdminPanel\Resources;

use App\Http\Controllers\Admin\ItemController;
use App\Models\Item;
use App\Services\AdminPanel\ClassicAction;
use App\Services\AdminPanel\Images;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Items. "Out of stock" only exists where the module tracks stock
 * (config/module.php), so a food item never shows as out.
 */
class ProductsResource extends Resource
{
    public ?string $permission = 'item';
    protected string $idColumn = 'i.id';
    protected ?string $merchantColumn = 'i.store_id';
    protected ?string $merchantNameSql = 's.name';
    protected array $search = ['i.name', 's.name', 'c.name'];
    protected array $sorts = ['name' => 'i.name', 'module' => 'i.module_id', 'category' => 'c.name', 'price' => 'i.price', 'stock' => 'i.stock'];

    public function config(): array
    {
        return [
            'label' => self::L('Products', 'المنتجات'), 'one' => self::L('Item', 'منتج'),
            'titleKey' => 'name', 'subKey' => 'merchant', 'statusKey' => 'status',
            'modKey' => 'module', 'merchantKey' => true, 'square' => true,
            'editable' => true, 'newUrl' => route('admin.item.add-new'), 'search' => ['name', 'merchant'],
            'filter' => ['key' => 'status', 'options' => ['active', 'out', 'pending', 'hidden']],
            'columns' => [
                ['key' => 'name', 'label' => self::L('Item', 'المنتج'), 'sub' => 'merchant', 'w' => '1.9fr', 'strong' => true, 'sort' => 'name'],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'fmt' => 'module', 'w' => '0.8fr', 'allOnly' => true, 'sort' => 'module'],
                ['key' => 'category', 'label' => self::L('Category', 'الفئة'), 'w' => '0.9fr', 'muted' => true, 'sort' => 'category'],
                ['key' => 'price', 'label' => self::L('Price', 'السعر'), 'fmt' => 'egp', 'w' => '0.8fr', 'sort' => 'price'],
                ['key' => 'stock', 'label' => self::L('Stock', 'المخزون'), 'fmt' => 'int', 'w' => '0.6fr', 'sort' => 'stock'],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'fmt' => 'status', 'w' => '0.9fr'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => self::L('Name', 'الاسم'), 'required' => true, 'wide' => true],
                ['key' => 'module', 'label' => self::L('Module', 'القسم'), 'readonly' => true, 'mod' => true],
                ['key' => 'merchant', 'label' => self::L('Merchant', 'المتجر'), 'readonly' => true],
                ['key' => 'category', 'label' => self::L('Category', 'الفئة'), 'readonly' => true],
                ['key' => 'price', 'label' => self::L('Price (EGP)', 'السعر (ج.م)'), 'type' => 'number', 'fmt' => 'egp', 'required' => true],
                ['key' => 'stock', 'label' => self::L('Stock', 'المخزون'), 'type' => 'number', 'fmt' => 'int'],
                ['key' => 'rating', 'label' => self::L('Rating', 'التقييم'), 'fmt' => 'rating', 'readonly' => true],
                ['key' => 'status', 'label' => self::L('Status', 'الحالة'), 'readonly' => true, 'status' => true],
                ['key' => 'description', 'label' => self::L('Description', 'الوصف'), 'type' => 'textarea', 'wide' => true],
            ],
        ];
    }

    /** Ids of modules whose type tracks stock. */
    private static function stockModules(): array
    {
        return DB::table('modules')->get(['id', 'module_type'])
            ->filter(fn ($m) => config('module.' . $m->module_type . '.stock'))->pluck('id')->all() ?: [0];
    }

    protected function statusSql(): string
    {
        return self::cases([
            'hidden' => 'i.status = 0',
            'pending' => 'i.is_approved = 0',
            'out' => 'i.stock <= 0 and i.module_id in (' . implode(',', self::stockModules()) . ')',
        ], 'active');
    }

    protected function query(string $mod): Builder
    {
        $q = DB::table('items as i')
            ->join('stores as s', 's.id', '=', 'i.store_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->select('i.id', 'i.name', 'i.image', 'i.price', 'i.stock', 'i.module_id', 'i.store_id', 'i.description', 'i.avg_rating', 'i.rating_count',
                's.name as store_name', 'c.name as category_name')
            ->selectRaw('(' . $this->statusSql() . ') as st');
        Images::selectDisk($q, Item::class, 'i.id', 'image', 'image_disk');
        if ($zone = Scope::adminZone()) {
            $q->where('s.zone_id', $zone);
        }
        return self::module($q, $mod, 'i.module_id');
    }

    protected function row(object $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'img' => $img = Images::url('product', $r->image, $r->image_disk),
            'avatars' => ['name' => $img],
            'module' => Scope::code($r->module_id),
            'merchant' => $r->store_name,
            'category' => $r->category_name,
            'price' => (float) $r->price,
            'stock' => (int) $r->stock,
            'rating' => $r->rating_count ? number_format($r->avg_rating, 1) : '—',
            'status' => $r->st,
            'description' => strip_tags((string) $r->description),
        ];
    }

    protected function classicUrl(array $row): ?string
    {
        return route('admin.item.view', ['id' => $row['id']]);
    }

    protected function actions(): array
    {
        $status = fn ($id, $s) => ClassicAction::run(ItemController::class, 'status', ['id' => $id, 'status' => $s], [], 'GET');
        return [
            ['key' => 'hide', 'label' => self::L('Hide from app', 'إخفاء من التطبيق'), 'tone' => 'neutral',
                'when' => fn ($x) => in_array($x['status'], ['active', 'out'], true), 'run' => fn ($x) => $status($x['id'], 0)],
            ['key' => 'publish', 'label' => self::L('Publish', 'نشر'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'hidden', 'run' => fn ($x) => $status($x['id'], 1)],
            ['key' => 'restock', 'label' => self::L('Restock (+50)', 'إعادة تخزين (+٥٠)'), 'tone' => 'primary',
                'when' => fn ($x) => $x['status'] === 'out',
                'run' => function ($x) {
                    DB::table('items')->where('id', $x['id'])->increment('stock', 50);
                    return self::ok(translate('messages.Stock_updated_successfully'));
                }],
        ];
    }

    public function update(int|string $id, array $data): array
    {
        $v = Validator::make($data, [
            'name' => 'required|string|max:191',
            'price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);
        if ($v->fails()) {
            return self::fail($v->errors()->first());
        }
        DB::table('items')->where('id', $id)->update([
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock'] ?? 0,
            'description' => $data['description'] ?? null,
            'updated_at' => now(),
        ]);
        self::syncTranslation(Item::class, $id, 'name', $data['name']);
        self::syncTranslation(Item::class, $id, 'description', $data['description'] ?? null);
        return self::ok(translate('messages.product_updated_successfully'));
    }
}
