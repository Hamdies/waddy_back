<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CAT-08: one convention for the positions in `category_ids`.
 *
 * The admin product form writes position 1 = category, 2 = sub-category, and
 * the item-list endpoints read it that way (CentralLogics/item.php picks
 * `position == 1` as the main category). The bulk importers and our seeders
 * wrote 0 / 1, so those rows surface their sub-category as the main one. The
 * importers now write 1 / 2; this rewrites the rows already stored.
 *
 * Only rows whose lowest position is 0 change, and every position shifts by
 * one. updated_at is left alone: the catalogue backfill uses it to pick the
 * freshest copy of a product, and this is not a content edit.
 */
class NormalizeCategoryIds extends Command
{
    protected $signature = 'catalog:normalize-category-ids
                            {--dry-run : Count and sample the rows that would change, without writing}';

    protected $description = 'Rewrite category_ids written with 0/1 positions to the admin 1/2 convention';

    private const TABLES = ['items', 'temp_products'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (self::TABLES as $table) {
            $this->normalizeTable($table, $dryRun);
        }

        if ($dryRun) {
            $this->line('Dry run — nothing written. Run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private function normalizeTable(string $table, bool $dryRun): void
    {
        $counts = ['scanned' => 0, 'shifted' => 0, 'already_1_2' => 0, 'unreadable' => 0];
        $samples = [];

        DB::table($table)
            ->whereNotNull('category_ids')
            ->select('id', 'category_ids')
            ->chunkById(500, function ($rows) use ($table, $dryRun, &$counts, &$samples) {
                foreach ($rows as $row) {
                    $counts['scanned']++;
                    $entries = $this->decode($row->category_ids);

                    if ($entries === null) {
                        $counts['unreadable']++;
                        continue;
                    }

                    $positions = array_map(fn ($entry) => (int) $entry['position'], $entries);
                    if (!$positions || min($positions) !== 0) {
                        $counts['already_1_2']++;
                        continue;
                    }

                    $shifted = array_map(
                        fn ($entry) => array_merge($entry, ['position' => (int) $entry['position'] + 1]),
                        $entries
                    );

                    $counts['shifted']++;
                    if (count($samples) < 5) {
                        $samples[] = "#{$row->id}: {$row->category_ids}  →  " . json_encode($shifted);
                    }

                    if (!$dryRun) {
                        DB::table($table)->where('id', $row->id)->update(['category_ids' => json_encode($shifted)]);
                    }
                }
            });

        $this->info($table);
        $this->table(array_keys($counts), [array_values($counts)]);
        foreach ($samples as $sample) {
            $this->line("  {$sample}");
        }
    }

    /**
     * Returns the entries, or null when the value is not a list of
     * {id, position}. Handles the double-encoded rows older writers left.
     */
    private function decode($value): ?array
    {
        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (!is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $entry) {
            if (!is_array($entry) || !array_key_exists('position', $entry) || !is_numeric($entry['position'])) {
                return null;
            }
        }

        return array_values($decoded);
    }
}
