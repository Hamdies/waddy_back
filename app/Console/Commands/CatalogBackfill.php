<?php

namespace App\Console\Commands;

use App\CentralLogics\Helpers;
use App\Models\CatalogProduct;
use App\Services\CatalogMatcher;
use App\Services\CatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * CAT phase 2: builds the catalogue from the supermarket listings that
 * already exist, and links each listing to its product.
 *
 * Listings are grouped by normalised name (CatalogMatcher). A group whose
 * members differ materially is flagged and left alone unless its key is
 * passed to --approve (CAT-13). Names that are only *similar* are listed as
 * possible matches and joined only with --merge. The unit is deliberately not
 * part of the key: live data has the same product with and without a unit
 * set (Garnier #1312), so a unit difference is a flag, not a split.
 *
 * Content comes from the richest member (photo > description > most recently
 * updated) and is copied onto every member; each member's old content goes to
 * catalog_content_backup first. No file is deleted (CAT-15). --rollback puts
 * the old content back.
 */
class CatalogBackfill extends Command
{
    protected $signature = 'catalog:backfill
                            {--dry-run : Print the grouping report without writing}
                            {--approve=* : Group keys to link even though flagged (comma-separated or repeated)}
                            {--merge=* : Group keys to join into one product, e.g. --merge=g1a2b3c,g4d5e6f (repeat per set)}
                            {--store=* : Limit to these store ids (default: every "Supermarkets" store)}
                            {--all : Also list single-store groups in the report}
                            {--rollback : Restore backed-up content and unlink}
                            {--product=* : With --rollback, only these catalogue product ids}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Create catalogue products from supermarket listings and link them (dry-run first)';

    /** Blocking: the group is not linked unless approved. */
    private const BLOCKING = ['same_store_twice', 'units_differ', 'price_spread', 'different_photos'];

    private const PRICE_SPREAD = 1.3;

    public function __construct(private CatalogService $catalog)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('rollback')) {
            return $this->rollback();
        }

        $listings = $this->loadListings();
        if ($listings->isEmpty()) {
            $this->info('No unlinked supermarket listings.');

            return self::SUCCESS;
        }

        $groups = $this->buildGroups($listings);
        $groups = $this->applyMerges($groups);
        $approved = $this->listOption('approve');

        foreach ($groups as $key => &$group) {
            $group['flags'] = $this->flags($group);
            $group['source'] = $this->pickSource($group['members']);
            $blocking = array_intersect($group['flags'], self::BLOCKING);
            $group['will_link'] = !$blocking || in_array($key, $approved, true) || $group['merged'];
        }
        unset($group);

        $unknown = array_diff($approved, array_keys($groups));
        foreach ($unknown as $key) {
            $this->warn("--approve {$key}: no such group in this run (already linked, or the name changed?)");
        }

        $possible = $this->possibleMatches($groups);
        $this->report($groups, $possible);
        $path = $this->writeReport($groups, $possible);
        $this->line("Report saved: {$path}");

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->line('Dry run — nothing written.');

            return self::SUCCESS;
        }

        $linkable = array_filter($groups, fn ($g) => $g['will_link']);
        $listingCount = array_sum(array_map(fn ($g) => count($this->perStoreWinners($g)), $linkable));

        $this->newLine();
        $this->warn('Take a database backup first — rollback is the fine-grained undo, the backup is the safety net.');
        if (!$this->option('force') && !$this->confirm("Link {$listingCount} listings into " . count($linkable) . ' catalogue products?')) {
            $this->line('Nothing written.');

            return self::SUCCESS;
        }

        $created = $joined = $linked = 0;
        foreach ($linkable as $group) {
            DB::transaction(function () use ($group, &$created, &$joined, &$linked) {
                if ($group['existing']) {
                    $product = $group['existing'];
                    $joined++;
                } else {
                    $source = $group['members']->firstWhere('id', $group['source']);
                    $product = $this->catalog->createFromListing(
                        $source,
                        CatalogMatcher::sizeToken($group['normalized'])
                    );
                    $created++;
                }

                foreach ($this->perStoreWinners($group) as $member) {
                    $this->catalog->linkListing($product, $member->id);
                    $linked++;
                }

                // Query builder, so updated_at is not bumped past it (drift check, CAT-17).
                DB::table('catalog_products')->where('id', $product->id)->update(['last_propagated_at' => now()]);
            });
        }

        $this->info("Done: {$created} catalogue products created, {$joined} existing joined, {$linked} listings linked.");
        $this->line('Undo with: php artisan catalog:backfill --rollback [--product=ID]');

        return self::SUCCESS;
    }

    private function loadListings(): Collection
    {
        $storeIds = $this->listOption('store') ?: Helpers::supermarketStoreIds();

        return DB::table('items')
            ->join('stores', 'stores.id', '=', 'items.store_id')
            ->whereIn('items.store_id', $storeIds)
            ->whereNull('items.catalog_product_id')
            ->orderBy('items.id')
            ->get([
                'items.id', 'items.store_id', 'items.module_id', 'items.name', 'items.description',
                'items.image', 'items.images', 'items.unit_id', 'items.category_id', 'items.category_ids',
                'items.price', 'items.updated_at', 'stores.name as store_name',
            ]);
    }

    private function buildGroups(Collection $listings): array
    {
        $existing = CatalogProduct::whereIn('module_id', $listings->pluck('module_id')->unique())
            ->get()
            ->keyBy(fn ($p) => CatalogMatcher::groupKey($p->module_id, CatalogMatcher::normalize($p->name)));

        $groups = [];
        foreach ($listings as $listing) {
            $normalized = CatalogMatcher::normalize($listing->name);
            $key = CatalogMatcher::groupKey((int) $listing->module_id, $normalized);

            $groups[$key] ??= [
                'key' => $key,
                'module_id' => (int) $listing->module_id,
                'normalized' => $normalized,
                'name' => $listing->name,
                'members' => collect(),
                'existing' => $existing->get($key),
                'merged' => false,
            ];
            $groups[$key]['members']->push($listing);
        }

        return $groups;
    }

    /** Each --merge set becomes one group under its first key. A merge is an explicit approval. */
    private function applyMerges(array $groups): array
    {
        foreach ($this->option('merge') as $set) {
            $keys = array_values(array_filter(array_map('trim', explode(',', $set))));
            $present = array_values(array_filter($keys, fn ($k) => isset($groups[$k])));

            if (count($present) < 2) {
                $this->warn("--merge {$set}: needs at least two groups from this run; skipped");
                continue;
            }

            $into = $present[0];
            foreach (array_slice($present, 1) as $key) {
                if ($groups[$key]['module_id'] !== $groups[$into]['module_id']) {
                    $this->warn("--merge {$set}: {$key} is in another module; skipped");
                    continue;
                }
                $groups[$into]['members'] = $groups[$into]['members']->merge($groups[$key]['members']);
                $groups[$into]['existing'] ??= $groups[$key]['existing'];
                unset($groups[$key]);
            }
            $groups[$into]['merged'] = true;
        }

        return $groups;
    }

    private function flags(array $group): array
    {
        $members = $group['members'];
        $flags = [];

        if ($members->countBy('store_id')->max() > 1) {
            $flags[] = 'same_store_twice';
        }

        if ($members->map(fn ($m) => $m->unit_id === null ? 'none' : (string) $m->unit_id)->unique()->count() > 1) {
            $flags[] = 'units_differ';
        }

        $prices = $members->pluck('price')->map(fn ($p) => (float) $p)->filter(fn ($p) => $p > 0);
        if ($prices->count() > 1 && $prices->max() / $prices->min() > self::PRICE_SPREAD) {
            $flags[] = 'price_spread';
        }

        $photos = $members->filter(fn ($m) => $this->hasPhoto($m))->pluck('image')->unique();
        if ($photos->count() > 1 && $photos->map(fn ($file) => $this->fileFingerprint($file))->unique()->count() > 1) {
            $flags[] = 'different_photos';
        }

        if ($members->pluck('category_id')->unique()->count() > 1) {
            $flags[] = 'category_differs';
        }

        return $flags;
    }

    /** Photo > description > most recently updated. */
    private function pickSource(Collection $members): int
    {
        return $members
            ->sortBy([
                fn ($a, $b) => $this->hasPhoto($b) <=> $this->hasPhoto($a),
                fn ($a, $b) => (trim((string) $b->description) !== '') <=> (trim((string) $a->description) !== ''),
                fn ($a, $b) => strcmp((string) $b->updated_at, (string) $a->updated_at),
            ])
            ->first()
            ->id;
    }

    /**
     * One listing per store (CAT-11's unique index). When a store has the
     * product twice, the richest copy links and the other stays unlinked.
     */
    private function perStoreWinners(array $group): array
    {
        return $group['members']
            ->groupBy('store_id')
            ->map(fn ($sameStore) => $sameStore->firstWhere('id', $this->pickSource($sameStore)))
            ->values()
            ->all();
    }

    private function possibleMatches(array $groups): array
    {
        $bySize = [];
        foreach ($groups as $key => $group) {
            $bySize[$group['module_id'] . '|' . (CatalogMatcher::sizeToken($group['normalized']) ?? '')][] = $key;
        }

        $pairs = [];
        foreach ($bySize as $keys) {
            for ($i = 0; $i < count($keys); $i++) {
                for ($j = $i + 1; $j < count($keys); $j++) {
                    $a = $groups[$keys[$i]];
                    $b = $groups[$keys[$j]];
                    if (CatalogMatcher::isPossibleMatch($a['normalized'], $b['normalized'])) {
                        $pairs[] = [$keys[$i], $keys[$j]];
                    }
                }
            }
        }

        return $pairs;
    }

    private function report(array $groups, array $possible): void
    {
        $showAll = (bool) $this->option('all');
        $multi = array_filter($groups, fn ($g) => $g['members']->count() > 1 || $g['existing'] || $g['flags']);
        $single = count($groups) - count($multi);

        $this->info('Groups sold at more than one store, flagged, or joining an existing product');
        foreach ($groups as $key => $group) {
            if (!$showAll && !isset($multi[$key])) {
                continue;
            }
            $this->printGroup($group);
        }

        if ($possible) {
            $this->newLine();
            $this->info('Possible matches — NOT merged. Same product? Join with --merge=KEY_A,KEY_B');
            foreach ($possible as [$a, $b]) {
                $this->line(sprintf(
                    '  %s  %-40s ~  %s  %s',
                    $a, $groups[$a]['name'] . ' (' . $groups[$a]['members']->count() . ')',
                    $b, $groups[$b]['name'] . ' (' . $groups[$b]['members']->count() . ')'
                ));
            }
        }

        $arabic = array_filter($groups, fn ($g) => CatalogMatcher::isArabic($g['name']));
        if ($arabic) {
            $this->newLine();
            $this->warn(count($arabic) . ' group(s) have an Arabic-only name and are never matched to English names automatically:');
            foreach ($arabic as $group) {
                $this->line("  {$group['key']}  {$group['name']}");
            }
        }

        $willLink = array_filter($groups, fn ($g) => $g['will_link']);
        $waiting = array_filter($groups, fn ($g) => !$g['will_link']);

        $this->newLine();
        $this->table(['groups', 'multi-store', 'single-store', 'will link', 'flagged, awaiting --approve', 'possible matches'], [[
            count($groups), count($multi), $single, count($willLink), count($waiting), count($possible),
        ]]);

        if ($waiting) {
            $this->line('Approve after checking: --approve=' . implode(',', array_keys($waiting)));
        }
    }

    private function printGroup(array $group): void
    {
        $status = $group['will_link'] ? '<fg=green>link</>' : '<fg=yellow>HOLD</>';
        $flags = $group['flags'] ? ' [' . implode(', ', $group['flags']) . ']' : '';
        $origin = $group['existing'] ? " → joins catalogue #{$group['existing']->id}" : '';
        $origin .= $group['merged'] ? ' (merged)' : '';

        $this->line("{$status}  {$group['key']}  <options=bold>{$group['name']}</>{$flags}{$origin}");

        $units = DB::table('units')->whereIn('id', $group['members']->pluck('unit_id')->filter())->pluck('unit', 'id');
        $winners = collect($this->perStoreWinners($group))->pluck('id')->all();

        foreach ($group['members'] as $member) {
            $marks = [];
            if ($member->id === $group['source'] && !$group['existing']) {
                $marks[] = 'SOURCE';
            }
            if (!in_array($member->id, $winners, true)) {
                $marks[] = 'stays unlinked (same store)';
            }

            $this->line(sprintf(
                '        #%-6d %-22s %8s  unit:%-10s cat:%-5s photo:%-3s desc:%-3s %s%s',
                $member->id,
                mb_strimwidth($member->store_name, 0, 22),
                number_format((float) $member->price, 2),
                $member->unit_id ? ($units[$member->unit_id] ?? $member->unit_id) : '-',
                $member->category_id ?? '-',
                $this->hasPhoto($member) ? 'yes' : 'no',
                trim((string) $member->description) !== '' ? 'yes' : 'no',
                $member->name !== $group['name'] ? "\"{$member->name}\" " : '',
                $marks ? '← ' . implode(', ', $marks) : ''
            ));
        }
    }

    private function writeReport(array $groups, array $possible): string
    {
        $path = 'catalog/backfill-' . now()->format('Ymd-His') . ($this->option('dry-run') ? '-dry' : '') . '.json';

        Storage::disk('local')->put($path, json_encode([
            'generated_at' => now()->toIso8601String(),
            'groups' => array_values(array_map(fn ($g) => [
                'key' => $g['key'],
                'name' => $g['name'],
                'normalized' => $g['normalized'],
                'will_link' => $g['will_link'],
                'flags' => $g['flags'],
                'source_item_id' => $g['existing'] ? null : $g['source'],
                'existing_catalog_product_id' => $g['existing']?->id,
                'members' => $g['members']->map(fn ($m) => [
                    'item_id' => $m->id, 'store_id' => $m->store_id, 'store' => $m->store_name,
                    'name' => $m->name, 'price' => (float) $m->price, 'unit_id' => $m->unit_id,
                    'category_id' => $m->category_id, 'image' => $m->image,
                ])->values()->all(),
            ], $groups)),
            'possible_matches' => $possible,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Storage::disk('local')->path($path);
    }

    private function rollback(): int
    {
        $productIds = array_map('intval', $this->listOption('product'));

        $listings = DB::table('items')
            ->whereNotNull('catalog_product_id')
            ->whereNotNull('catalog_content_backup')
            ->when($productIds, fn ($q) => $q->whereIn('catalog_product_id', $productIds))
            ->get(['id', 'catalog_product_id']);

        if ($listings->isEmpty()) {
            $this->info('Nothing to roll back.');

            return self::SUCCESS;
        }

        $products = $listings->pluck('catalog_product_id')->unique();
        if (!$this->option('force') && !$this->confirm("Restore {$listings->count()} listings and unlink them from {$products->count()} catalogue products?")) {
            $this->line('Nothing written.');

            return self::SUCCESS;
        }

        $restored = $removed = 0;
        DB::transaction(function () use ($listings, $products, &$restored, &$removed) {
            foreach ($listings as $listing) {
                $restored += (int) $this->catalog->restoreListing($listing->id);
            }

            // Only products this rollback emptied: a catalogue product made in
            // the admin with no listings yet is not ours to delete. Rows only —
            // their files stay (CAT-15).
            $empty = CatalogProduct::whereIn('id', $products)->whereDoesntHave('listings')->pluck('id');
            DB::table('translations')
                ->where('translationable_type', CatalogProduct::class)
                ->whereIn('translationable_id', $empty)
                ->delete();
            $removed = CatalogProduct::whereIn('id', $empty)->delete();
        });

        $this->info("Restored {$restored} listings; removed {$removed} catalogue products left without listings. No files were deleted.");

        return self::SUCCESS;
    }

    private function hasPhoto(object $listing): bool
    {
        return !empty($listing->image) && $listing->image !== 'def.png';
    }

    /** Same bytes under two names is one photo. Unreadable files count as different. */
    private function fileFingerprint(string $file): string
    {
        // s3 only where it is configured: exists() against an unconfigured
        // bucket can hang for minutes.
        $disks = Helpers::getDisk() === 's3' ? ['public', 's3'] : ['public'];
        foreach ($disks as $disk) {
            try {
                if (Storage::disk($disk)->exists("product/{$file}")) {
                    return Storage::disk($disk)->checksum("product/{$file}");
                }
            } catch (\Throwable $e) {
            }
        }

        return "missing:{$file}";
    }

    private function listOption(string $name): array
    {
        return array_values(array_filter(array_map('trim', explode(',', implode(',', $this->option($name))))));
    }
}
