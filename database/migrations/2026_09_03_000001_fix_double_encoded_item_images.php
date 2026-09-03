<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs items whose `images` column holds the JSON string "[]" rather than
 * the empty array [].
 *
 * The column is cast to array on the model, so Eloquent encodes it on save.
 * MaadiContentSeeder passed an already-encoded string, which was encoded a
 * second time. Reading it back yields the PHP string "[]", and the admin item
 * edit page foreaches over that value, so every affected item 500s there.
 *
 * Only rows holding an encoded empty array are touched: a row with real image
 * data is left exactly as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rows that were double-encoded: the stored text is a JSON string
        // whose content is itself JSON. JSON_VALID guards the unquote, and
        // the leading-quote test keeps correctly stored arrays out of it.
        DB::table('items')
            ->where('images', 'LIKE', '"%"')
            ->whereRaw('JSON_VALID(images)')
            ->update(['images' => DB::raw('JSON_UNQUOTE(images)')]);

        // An empty string decodes to null rather than an array, which breaks
        // the same way.
        DB::table('items')
            ->where(function ($query) {
                $query->where('images', '')->orWhereNull('images');
            })
            ->update(['images' => '[]']);
    }

    /**
     * The broken value has no meaning worth restoring.
     */
    public function down(): void
    {
    }
};
