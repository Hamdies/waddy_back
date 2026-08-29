<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

trait FileManagerTrait
{
    public static function upload(string $dir, string $format, $image = null): string
    {
        $imageName = 'def.png';
        try {
            if ($image != null) {
                $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
                if (!Storage::disk(self::getDisk())->exists($dir)) {
                    Storage::disk(self::getDisk())->makeDirectory($dir);
                }
                Storage::disk(self::getDisk())->putFileAs($dir, $image, $imageName);
                \App\CentralLogics\Helpers::queue_image_variants($dir, $imageName);
            }
        } catch (\Exception $e) {
        }

        return $imageName;
    }

    public static function updateAndUpload(string $dir, $old_image, string $format, $image = null): mixed
    {
//        dd(self::getDisk());
        if ($image == null) {
            return $old_image;
        }
        try {
            if (Storage::disk(self::getDisk())->exists($dir . $old_image)) {
                Storage::disk(self::getDisk())->delete($dir . $old_image);
            }
        } catch (\Exception $e) {
        }
        \App\CentralLogics\Helpers::delete_image_variants($dir, $old_image);

        return self::upload($dir, $format, $image);
    }

    public static function getDisk(): string
    {
        $config=\App\CentralLogics\Helpers::get_business_settings('local_storage');

        return isset($config)?($config==0?'s3':'public'):'public';
    }
}
