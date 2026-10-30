<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on groups owned by a store, with a pick rule (required/optional, min, max).
 *
 * Admin categories stay as they are (store_id null). Every store's add-ons are
 * moved into a store group copied from the admin category they were in, so each
 * store can change names and rules without touching other stores.
 * source_category_id remembers the admin category so down() can move them back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addon_categories', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('module_id')->index();
            $table->boolean('is_required')->default(0)->after('status');
            $table->unsignedTinyInteger('min_choices')->default(0)->after('is_required');
            $table->unsignedTinyInteger('max_choices')->nullable()->after('min_choices');
            $table->unsignedBigInteger('source_category_id')->nullable()->after('max_choices');
        });

        $pairs = DB::table('add_ons')
            ->whereNotNull('store_id')
            ->select('store_id', 'addon_category_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $source = $pair->addon_category_id ? DB::table('addon_categories')->find($pair->addon_category_id) : null;
            $moduleId = DB::table('stores')->where('id', $pair->store_id)->value('module_id');

            $groupId = DB::table('addon_categories')->insertGetId([
                'name' => $source->name ?? 'Add-ons',
                'module_id' => $moduleId,
                'store_id' => $pair->store_id,
                'status' => 1,
                'is_required' => 0,
                'min_choices' => 0,
                'max_choices' => null,
                'source_category_id' => $pair->addon_category_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('add_ons')
                ->where('store_id', $pair->store_id)
                ->when($pair->addon_category_id, fn ($q) => $q->where('addon_category_id', $pair->addon_category_id), fn ($q) => $q->whereNull('addon_category_id'))
                ->update(['addon_category_id' => $groupId]);
        }
    }

    public function down(): void
    {
        $groups = DB::table('addon_categories')->whereNotNull('store_id')->get();
        foreach ($groups as $group) {
            DB::table('add_ons')->where('addon_category_id', $group->id)->update(['addon_category_id' => $group->source_category_id]);
        }
        DB::table('addon_categories')->whereNotNull('store_id')->delete();

        Schema::table('addon_categories', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
            $table->dropColumn(['store_id', 'is_required', 'min_choices', 'max_choices', 'source_category_id']);
        });
    }
};
