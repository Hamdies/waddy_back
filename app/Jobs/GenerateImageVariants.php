<?php

namespace App\Jobs;

use App\Services\ImageVariantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Encodes the variant set for one uploaded image.
 *
 * One job per image rather than one per batch: a single bad file then fails
 * alone instead of taking a backfill of thousands down with it, and the queue
 * can interleave uploads with backfill work.
 *
 * Note that with QUEUE_CONNECTION=sync (local, and anywhere the worker is not
 * running) this executes inline inside the upload request, adding a few
 * hundred milliseconds to an admin save. That is the correct trade — the
 * alternative is a job that silently never runs.
 */
class GenerateImageVariants implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public string $disk,
        public string $dir,
        public string $filename,
        public bool $force = false,
    ) {
    }

    public function handle(ImageVariantService $variants): void
    {
        $variants->generate($this->disk, $this->dir, $this->filename, $this->force);
    }

    /**
     * Collapses duplicate work when the same image is saved twice in quick
     * succession. Only takes effect on drivers that support unique jobs.
     */
    public function uniqueId(): string
    {
        return $this->disk . ':' . trim($this->dir, '/') . '/' . $this->filename;
    }
}
