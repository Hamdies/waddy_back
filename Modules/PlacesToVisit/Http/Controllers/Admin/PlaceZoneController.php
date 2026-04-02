<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Models\Zone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlaceZoneController extends Controller
{
    /**
     * List all zones with option to create new ones.
     */
    public function index(): View
    {
        $zones = Zone::withoutGlobalScopes()
            ->with(['translations'])
            ->orderBy('name')
            ->get();

        $language = getWebConfig('language');

        return view('placestovisit::admin.zones.index', compact('zones', 'language'));
    }

    /**
     * Show zone creation form.
     */
    public function create(): View
    {
        $language = getWebConfig('language');
        return view('placestovisit::admin.zones.create', compact('language'));
    }

    /**
     * Store a new zone.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'display_name' => 'required|string|max:200',
        ]);

        $zone = Zone::withoutGlobalScopes()->create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'status' => 1,
        ]);

        // Store translations for each language
        $this->saveTranslations($request, $zone);

        \Toastr::success(translate('messages.zone_created_successfully'));
        return redirect()->route('admin.places.zones.index');
    }

    /**
     * Show zone edit form.
     */
    public function edit(int $id): View
    {
        $zone = Zone::withoutGlobalScopes()->with('translations')->findOrFail($id);
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());

        return view('placestovisit::admin.zones.edit', compact('zone', 'language', 'defaultLang'));
    }

    /**
     * Update a zone.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'display_name' => 'required|string|max:200',
        ]);

        $zone = Zone::withoutGlobalScopes()->findOrFail($id);

        $zone->update([
            'name' => $request->name,
            'display_name' => $request->display_name,
        ]);

        // Update translations
        $this->saveTranslations($request, $zone);

        \Toastr::success(translate('messages.zone_updated_successfully'));
        return redirect()->route('admin.places.zones.index');
    }

    /**
     * Delete a zone (only if no places reference it).
     */
    public function destroy(int $id): RedirectResponse
    {
        $zone = Zone::withoutGlobalScopes()->findOrFail($id);

        $placesCount = \Modules\PlacesToVisit\Entities\Place::where('zone_id', $id)->count();
        if ($placesCount > 0) {
            \Toastr::error(translate('messages.cannot_delete_zone_with_places'));
            return back();
        }

        // Delete translations
        Translation::where('translationable_type', 'App\Models\Zone')
            ->where('translationable_id', $zone->id)
            ->delete();

        $zone->delete();

        \Toastr::success(translate('messages.zone_deleted_successfully'));
        return redirect()->route('admin.places.zones.index');
    }

    /**
     * Save translations for name and display_name.
     */
    private function saveTranslations(Request $request, Zone $zone): void
    {
        $languages = getWebConfig('language') ?? [];

        foreach ($languages as $lang) {
            $locale = $lang['code'] ?? $lang;
            $defaultLang = str_replace('_', '-', app()->getLocale());

            if ($locale === $defaultLang) {
                continue;
            }

            foreach (['name', 'display_name'] as $attribute) {
                $value = $request->input("{$attribute}_{$locale}");

                if ($value) {
                    Translation::updateOrCreate(
                        [
                            'translationable_type' => 'App\Models\Zone',
                            'translationable_id' => $zone->id,
                            'locale' => $locale,
                            'key' => $attribute,
                        ],
                        ['value' => $value]
                    );
                }
            }
        }
    }
}
