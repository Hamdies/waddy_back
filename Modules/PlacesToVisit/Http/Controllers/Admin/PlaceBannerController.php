<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\CentralLogics\Helpers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceBanner;
use Modules\PlacesToVisit\Entities\PlaceCategory;

class PlaceBannerController extends Controller
{
    public function index(Request $request): View
    {
        $banners = PlaceBanner::query()
            ->with(['zone'])
            ->when($request->search, function ($q, $search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%");
            })
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->status !== null, function ($q) use ($request) {
                $q->where('is_active', $request->status == '1');
            })
            ->ordered()
            ->paginate(config('default_pagination'));

        $zones = Zone::active()->get();

        return view('placestovisit::admin.banners.index', compact('banners', 'zones'));
    }

    public function create(): View
    {
        $categories = PlaceCategory::active()->ordered()->get();
        $places = Place::active()->with('translations')->get();
        $zones = Zone::active()->get();
        
        return view('placestovisit::admin.banners.create', compact('categories', 'places', 'zones'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'title_ar' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:500',
            'description_ar' => 'nullable|string|max:500',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'type' => 'required|in:default,category,place,external',
            'data' => 'nullable|integer',
            'external_link' => 'nullable|url|max:500',
            'zone_id' => 'nullable|exists:zones,id',
            'priority' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $imagePath = Helpers::upload('place_banner/', 'png', $request->file('image'));

        PlaceBanner::create([
            'title' => $request->title,
            'title_ar' => $request->title_ar,
            'description' => $request->description,
            'description_ar' => $request->description_ar,
            'image' => $imagePath,
            'type' => $request->type,
            'data' => $request->data,
            'external_link' => $request->external_link,
            'zone_id' => $request->zone_id,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->has('is_active'),
            'is_featured' => $request->has('is_featured'),
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        \Toastr::success(translate('messages.banner_created_successfully'));
        return redirect()->route('admin.places.banners.index');
    }

    public function edit(PlaceBanner $banner): View
    {
        $categories = PlaceCategory::active()->ordered()->get();
        $places = Place::active()->with('translations')->get();
        $zones = Zone::active()->get();
        
        return view('placestovisit::admin.banners.edit', compact('banner', 'categories', 'places', 'zones'));
    }

    public function update(Request $request, PlaceBanner $banner): RedirectResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'title_ar' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:500',
            'description_ar' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'type' => 'required|in:default,category,place,external',
            'data' => 'nullable|integer',
            'external_link' => 'nullable|url|max:500',
            'zone_id' => 'nullable|exists:zones,id',
            'priority' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $imagePath = $banner->image;
        if ($request->hasFile('image')) {
            Helpers::delete('place_banner/' . $imagePath);
            $imagePath = Helpers::upload('place_banner/', 'png', $request->file('image'));
        }

        $banner->update([
            'title' => $request->title,
            'title_ar' => $request->title_ar,
            'description' => $request->description,
            'description_ar' => $request->description_ar,
            'image' => $imagePath,
            'type' => $request->type,
            'data' => $request->data,
            'external_link' => $request->external_link,
            'zone_id' => $request->zone_id,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->has('is_active'),
            'is_featured' => $request->has('is_featured'),
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        \Toastr::success(translate('messages.banner_updated_successfully'));
        return redirect()->route('admin.places.banners.index');
    }

    public function destroy(PlaceBanner $banner): RedirectResponse
    {
        if ($banner->image) {
            Helpers::delete('place_banner/' . $banner->image);
        }
        
        $banner->delete();
        
        \Toastr::success(translate('messages.banner_deleted_successfully'));
        return redirect()->route('admin.places.banners.index');
    }

    public function toggleStatus(PlaceBanner $banner): RedirectResponse
    {
        $banner->update(['is_active' => !$banner->is_active]);
        
        \Toastr::success(translate('messages.status_updated'));
        return back();
    }

    public function toggleFeatured(PlaceBanner $banner): RedirectResponse
    {
        $banner->update(['is_featured' => !$banner->is_featured]);
        
        \Toastr::success(translate('messages.featured_status_updated'));
        return back();
    }
}
