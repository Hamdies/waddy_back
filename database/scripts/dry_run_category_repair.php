<?php

/**
 * Read-only preview of 2026_09_02_000001_repair_grocery_category_tree.
 *
 * Prints the tree as it stands and as the migration would leave it. Runs no
 * writes and takes no locks, so it is safe against production.
 *
 *   php8.2 artisan tinker --execute="require 'database/scripts/dry_run_category_repair.php';"
 *
 * Delete this file once the repair has been applied and verified.
 */

use Illuminate\Support\Facades\DB;

$moduleId = DB::table('modules')->where('module_type', 'grocery')->value('id');

if (!$moduleId) {
    echo "No grocery module found.\n";

    return;
}

$categories = DB::table('categories')->where('module_id', $moduleId)->get();
$topLevel = $categories->where('parent_id', 0);
$children = $categories->where('parent_id', '!=', 0);

echo "=== BEFORE ===\n";
echo "module_id: {$moduleId}\n";
echo "top-level categories: " . $topLevel->count() . "\n";

foreach ($topLevel as $row) {
    $count = $children->where('parent_id', $row->id)->count();
    echo sprintf("  [%d] %-28s %d children\n", $row->id, $row->name, $count);
}

echo "orphaned subcategories: " . $children->count() . "\n\n";

$demoTranslation = DB::table('translations')
    ->where('translationable_type', 'App\Models\Category')
    ->where('translationable_id', 1)
    ->where('locale', 'en')
    ->where('key', 'name')
    ->value('value');

if ($demoTranslation !== null) {
    echo "category 1 English translation currently renders as: \"{$demoTranslation}\"\n\n";
}

$migration = require __DIR__ . '/../migrations/2026_09_02_000001_repair_grocery_category_tree.php';
$reflection = new ReflectionClass($migration);

$topLevelNames = $reflection->getConstant('TOP_LEVEL');
$mapping = $reflection->getConstant('SUBCATEGORY_PARENT');
$strays = $reflection->getConstant('STRAY_SUBCATEGORIES');

echo "=== AFTER ===\n";

$existingTopNames = $topLevel->pluck('name')->all();
$created = 0;

foreach (array_keys($topLevelNames) as $name) {
    $isNew = !in_array($name, $existingTopNames, true);
    $created += $isNew ? 1 : 0;

    $incoming = collect($mapping)->filter(fn ($parent) => $parent === $name)->keys()
        ->filter(fn ($child) => $children->contains('name', $child))
        ->count();

    echo sprintf("  %-28s %-9s %d children\n", $name, $isNew ? '(new)' : '(exists)', $incoming);
}

echo "\ntop-level categories to create: {$created}\n";

$moved = collect($mapping)
    ->filter(fn ($parent, $child) => $children->contains('name', $child))
    ->count();

echo "subcategories to re-parent: {$moved}\n";

$toHide = $categories
    ->filter(fn ($row) => $row->parent_id != 0
        && (in_array($row->name, $strays, true) || array_key_exists($row->name, $topLevelNames)))
    ->count();

echo "stray rows to hide (status=0, not deleted): {$toHide}\n";

// A subcategory sharing a name with one of the new top-level categories is
// a duplicate of it, hidden at the root by the migration rather than
// re-parented, so it is handled even though no mapping names it.
$unmapped = $children
    ->reject(fn ($row) => isset($mapping[$row->name])
        || in_array($row->name, $strays, true)
        || array_key_exists($row->name, $topLevelNames));

if ($unmapped->count() > 0) {
    echo "\nWARNING - subcategories with no mapping, these stay where they are:\n";
    foreach ($unmapped as $row) {
        echo "  [{$row->id}] {$row->name}\n";
    }
}

$itemCount = DB::table('items')->where('module_id', $moduleId)->count();
echo "\nitems in this module: {$itemCount} (none are modified by the repair)\n";
