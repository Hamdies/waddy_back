<?php

namespace App\Console\Commands;

use App\Jobs\GenerateImageVariants;
use App\Services\ImageVariantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillImageVariants extends Command
{
    protected $signature = 'images:backfill-variants
        {--disk= : Storage disk to walk. Defaults to whatever Helpers::getDisk() resolves to}
        {--dir=* : Limit to these upload directories (default: every directory in config/imagevariants.php)}
        {--force : Regenerate even where a variant set already exists}
        {--limit=0 : Stop after this many images}
        {--sync : Encode inline instead of queueing, for a small run or a box with no worker}
        {--dry-run : List what would be queued and exit}';

    protected $description = 'Generate WebP variants for images uploaded before variants existed';

    public function handle(ImageVariantService $variants): int
    {
        if (!$variants->enabled()) {
            $this->error('Image variants are disabled. Set IMAGE_VARIANTS_ENABLED=true to run this.');

            return self::FAILURE;
        }

        $disk = $this->option('disk') ?: \App\CentralLogics\Helpers::getDisk();
        $dirs = $this->option('dir') ?: array_keys(config('imagevariants.directories', []));
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync');
        $dryRun = (bool) $this->option('dry-run');

        $this->line("disk: {$disk}");

        $variantRoot = config('imagevariants.variant_root', 'variants');
        $queued = 0;
        $skipped = 0;

        foreach ($dirs as $dir) {
            $dir = trim($dir, '/');

            if ($variants->sizesFor($dir) === null) {
                $this->warn("  {$dir}: not configured for variants, skipping");
                continue;
            }

            try {
                // files() is deliberately non-recursive: it lists the images in
                // this directory without descending into variants/ (or into
                // store/cover, which is configured separately with its own
                // sizes and would otherwise be processed twice).
                $files = Storage::disk($disk)->files($dir);
            } catch (\Throwable $e) {
                $this->warn("  {$dir}: unreadable ({$e->getMessage()})");
                continue;
            }

            $dirQueued = 0;

            foreach ($files as $path) {
                $filename = basename($path);

                if (str_contains($path, "/{$variantRoot}/") || !$variants->isSupportedSource($filename)) {
                    continue;
                }

                if (!$force && $variants->variantsPresent($disk, $dir, $filename)) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  would process {$path}");
                    $dirQueued++;
                    $queued++;
                } elseif ($sync) {
                    $written = $variants->generate($disk, $dir, $filename, $force);
                    $dirQueued++;
                    $queued++;
                    $this->line(sprintf('  %s -> %d file(s)', $path, count($written)));
                } else {
                    GenerateImageVariants::dispatch($disk, $dir, $filename, $force)
                        ->onQueue(config('imagevariants.queue', 'default'));
                    $dirQueued++;
                    $queued++;
                }

                if ($limit > 0 && $queued >= $limit) {
                    $this->info("  {$dir}: {$dirQueued}");
                    $this->info("Reached --limit={$limit}.");

                    return $this->summary($queued, $skipped, $sync, $dryRun);
                }
            }

            $this->info("  {$dir}: {$dirQueued}");
        }

        return $this->summary($queued, $skipped, $sync, $dryRun);
    }

    private function summary(int $queued, int $skipped, bool $sync, bool $dryRun): int
    {
        $verb = $dryRun ? 'would process' : ($sync ? 'encoded' : 'queued');
        $this->newLine();
        $this->info("{$verb}: {$queued}   already had variants: {$skipped}");

        if (!$sync && !$dryRun && $queued > 0) {
            $this->line('Watch the worker with: journalctl -u waddy-queue -f');
        }

        return self::SUCCESS;
    }
}
