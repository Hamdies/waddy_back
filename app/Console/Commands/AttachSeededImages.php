<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Attaches dropped-in image files to stores, items and places by matching
 * filenames against record names.
 *
 * Drop files into an inbox directory named after what they depict — "Zooba
 * Maadi.jpg", "seoudi-market-maadi.png", "Grilled Sea Bass.webp" — and this
 * files them into the right storage directory under a safe name and points
 * the record at them. Matching is on a normalised slug, so spacing,
 * punctuation and case do not have to be exact.
 */
class AttachSeededImages extends Command
{
    protected $signature = 'waddy:attach-images
                            {--inbox=storage/app/image-inbox : Directory holding the files to attach}
                            {--dry-run : Report the matches without copying or writing}';

    protected $description = 'Match dropped-in images to stores, items and places by filename';

    /** Where each kind of image belongs on the public disk. */
    private const TARGET_DIRS = [
        'store' => 'store',
        'cover' => 'store/cover',
        'item' => 'product',
        'place' => 'places',
    ];

    private const SUPPORTED = ['jpg', 'jpeg', 'png', 'webp'];

    public function handle(): int
    {
        $inbox = $this->option('inbox');
        $inbox = str_starts_with($inbox, '/') ? $inbox : base_path($inbox);
        $dryRun = (bool) $this->option('dry-run');

        if (!File::isDirectory($inbox)) {
            $this->error("Inbox not found: {$inbox}");
            $this->line('Create it and drop your images in, then run this again.');

            return self::FAILURE;
        }

        $files = collect(File::files($inbox))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), self::SUPPORTED, true));

        if ($files->isEmpty()) {
            $this->warn("No images found in {$inbox}");

            return self::SUCCESS;
        }

        $this->info("Found {$files->count()} image(s) in {$inbox}");

        if ($dryRun) {
            $this->warn('Dry run: nothing will be copied or written.');
        }

        $index = $this->buildIndex();
        $matched = 0;
        $unmatched = [];

        foreach ($files as $file) {
            $slug = $this->normalise($file->getFilenameWithoutExtension());
            $target = $index[$slug] ?? null;

            if (!$target) {
                $unmatched[] = $file->getFilename();
                continue;
            }

            $matched++;
            $this->line(sprintf(
                '  %-40s -> %s [%d] %s',
                $file->getFilename(),
                $target['type'],
                $target['id'],
                $target['name'],
            ));

            if (!$dryRun) {
                $this->attach($file->getPathname(), $file->getExtension(), $target);
            }
        }

        $this->newLine();
        $this->info("Matched {$matched} of {$files->count()}.");

        if ($unmatched) {
            $this->newLine();
            $this->warn('No record matched these, so they were left in the inbox:');
            foreach ($unmatched as $name) {
                $this->line("  {$name}");
            }
            $this->line('Rename them to match a store, item or place name and run again.');
        }

        if (!$dryRun && $matched > 0) {
            $this->newLine();
            $this->info('Done. Files copied and records updated.');
        }

        return self::SUCCESS;
    }

    /**
     * Every attachable record, keyed by a normalised form of its name.
     *
     * A "-cover" suffix on the filename targets a store's cover photo rather
     * than its logo, which is the only case where one record takes two images.
     *
     * @return array<string,array{type:string,id:int,name:string,field:string,dir:string}>
     */
    private function buildIndex(): array
    {
        $index = [];

        // Queried through the query builder rather than the models: matching
        // is on the stored name, and the models' global scopes and appended
        // attributes bring in unrelated tables this command has no use for.
        foreach (DB::table('stores')->get(['id', 'name']) as $store) {
            $key = $this->normalise($store->name);

            $index[$key] = [
                'type' => 'store logo',
                'id' => $store->id,
                'name' => $store->name,
                'field' => 'logo',
                'dir' => self::TARGET_DIRS['store'],
            ];

            $index[$key . '-cover'] = [
                'type' => 'store cover',
                'id' => $store->id,
                'name' => $store->name,
                'field' => 'cover_photo',
                'dir' => self::TARGET_DIRS['cover'],
            ];
        }

        foreach (DB::table('items')->get(['id', 'name']) as $item) {
            // Items are keyed after stores, so a store and an item sharing a
            // name resolves to the store. Renaming the file disambiguates.
            $index[$this->normalise($item->name)] ??= [
                'type' => 'item',
                'id' => $item->id,
                'name' => $item->name,
                'field' => 'image',
                'dir' => self::TARGET_DIRS['item'],
            ];
        }

        $places = DB::table('places')
            ->join('place_translations', 'places.id', '=', 'place_translations.place_id')
            ->where('place_translations.locale', 'en')
            ->select('places.id', 'place_translations.title')
            ->get();

        foreach ($places as $place) {
            $key = $this->normalise($place->title);

            $index[$key] ??= [
                'type' => 'place',
                'id' => $place->id,
                'name' => $place->title,
                'field' => 'image',
                'dir' => self::TARGET_DIRS['place'],
            ];

            $index[$key . '-cover'] ??= [
                'type' => 'place cover',
                'id' => $place->id,
                'name' => $place->title,
                'field' => 'cover_image',
                'dir' => self::TARGET_DIRS['place'],
            ];
        }

        return $index;
    }

    /**
     * Copies the file onto the public disk and points the record at it.
     */
    private function attach(string $source, string $extension, array $target): void
    {
        $directory = storage_path('app/public/' . $target['dir']);
        File::ensureDirectoryExists($directory);

        // items.image is varchar(30), so the stored name has to stay short:
        // a date prefix plus a short random suffix, as the admin panel does.
        $filename = now()->format('Y-m-d') . '-' . Str::random(10) . '.' . strtolower($extension);

        File::copy($source, $directory . '/' . $filename);

        match ($target['type']) {
            'store logo', 'store cover' => DB::table('stores')
                ->where('id', $target['id'])
                ->update([$target['field'] => $filename, 'updated_at' => now()]),
            'item' => DB::table('items')
                ->where('id', $target['id'])
                ->update([$target['field'] => $filename, 'updated_at' => now()]),
            'place', 'place cover' => DB::table('places')
                ->where('id', $target['id'])
                ->update([$target['field'] => $filename, 'updated_at' => now()]),
        };
    }

    /**
     * Filenames are matched loosely: case, spacing, punctuation and any
     * trailing counter ("zooba-1.jpg") are ignored.
     */
    private function normalise(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/\s*\(\d+\)$/', '', $value);
        $value = preg_replace('/[\'’`]/u', '', $value);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
        $value = trim($value, '-');

        // A trailing counter is noise, but "-cover" is meaningful.
        if (!Str::endsWith($value, '-cover')) {
            $value = preg_replace('/-\d+$/', '', $value);
        }

        return $value;
    }
}
