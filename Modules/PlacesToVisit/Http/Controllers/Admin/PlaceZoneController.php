<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\PlaceZone;

class PlaceZoneController extends Controller
{
    /**
     * List all zones.
     */
    public function index(): View
    {
        $zones = PlaceZone::query()
            ->withCount('places')
            ->when(request('search'), fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(config('default_pagination', 15));

        return view('placestovisit::admin.zones.index', compact('zones'));
    }

    /**
     * Show zone creation form.
     */
    public function create(): View
    {
        return view('placestovisit::admin.zones.create');
    }

    /**
     * Store a new zone.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'name_ar' => 'nullable|string|max:200',
            'display_name' => 'required|string|max:200',
            'display_name_ar' => 'nullable|string|max:200',
        ]);

        PlaceZone::create([
            'name' => $request->name,
            'name_ar' => $request->name_ar ?: null,
            'display_name' => $request->display_name,
            'display_name_ar' => $request->display_name_ar ?: null,
        ]);

        \Toastr::success(translate('messages.zone_created_successfully'));
        return redirect()->route('admin.places.zones.index');
    }

    /**
     * Show zone edit form.
     */
    public function edit(PlaceZone $zone): View
    {
        return view('placestovisit::admin.zones.edit', compact('zone'));
    }

    /**
     * Update a zone.
     */
    public function update(Request $request, PlaceZone $zone): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'name_ar' => 'nullable|string|max:200',
            'display_name' => 'required|string|max:200',
            'display_name_ar' => 'nullable|string|max:200',
        ]);

        $zone->update([
            'name' => $request->name,
            'name_ar' => $request->name_ar ?: null,
            'display_name' => $request->display_name,
            'display_name_ar' => $request->display_name_ar ?: null,
        ]);

        \Toastr::success(translate('messages.zone_updated_successfully'));
        return redirect()->route('admin.places.zones.index');
    }

    /**
     * Delete a zone.
     */
    public function destroy(PlaceZone $zone): RedirectResponse
    {
        if ($zone->places()->exists()) {
            \Toastr::error(translate('messages.cannot_delete_zone_with_places'));
            return back();
        }

        $zone->delete();

        \Toastr::success(translate('messages.zone_deleted_successfully'));
        return redirect()->route('admin.places.zones.index');
    }

    /**
     * Toggle zone active status.
     */
    public function toggleStatus(PlaceZone $zone): RedirectResponse
    {
        $zone->update(['is_active' => !$zone->is_active]);

        \Toastr::success(translate('messages.status_updated'));
        return back();
    }
}
