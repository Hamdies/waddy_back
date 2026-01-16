<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceCategory;
use Modules\PlacesToVisit\Entities\PlaceTranslation;
use App\CentralLogics\Helpers;

class PlaceController extends Controller
{
    public function index(Request $request): View
    {
        $period = now()->format('Y-m');
        
        $places = Place::query()
            ->with(['translations', 'category'])
            ->withCurrentPeriodStats($period)
            ->when($request->search, function ($q, $search) {
                $q->whereHas('translations', fn($tq) => 
                    $tq->where('title', 'like', "%{$search}%")
                );
            })
            ->when($request->category_id, fn($q, $catId) => $q->where('category_id', $catId))
            ->latest()
            ->paginate(config('default_pagination'));

        $categories = PlaceCategory::active()->ordered()->get();

        return view('placestovisit::admin.places.index', compact('places', 'categories'));
    }

    public function create(): View
    {
        $categories = PlaceCategory::active()->ordered()->get();
        return view('placestovisit::admin.places.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'category_id' => 'required|exists:place_categories,id',
            'title_en' => 'required|string|max:200',
            'title_ar' => 'nullable|string|max:200',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = Helpers::upload('places/', 'png', $request->file('image'));
        }

        $place = Place::create([
            'category_id' => $request->category_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'image' => $imagePath,
            'is_active' => $request->has('is_active'),
            'is_featured' => $request->has('is_featured'),
        ]);

        // Create English translation
        PlaceTranslation::create([
            'place_id' => $place->id,
            'locale' => 'en',
            'title' => $request->title_en,
            'description' => $request->description_en,
        ]);

        // Create Arabic translation if provided
        if ($request->title_ar) {
            PlaceTranslation::create([
                'place_id' => $place->id,
                'locale' => 'ar',
                'title' => $request->title_ar,
                'description' => $request->description_ar,
            ]);
        }

        \Toastr::success(translate('messages.place_created_successfully'));
        return redirect()->route('admin.places.index');
    }

    public function show(Place $place): RedirectResponse
    {
        return redirect()->route('admin.places.edit', $place->id);
    }

    public function edit(Place $place): View
    {
        $place->load('translations');
        $categories = PlaceCategory::active()->ordered()->get();
        
        $translations = $place->translations->keyBy('locale');
        
        return view('placestovisit::admin.places.edit', compact('place', 'categories', 'translations'));
    }

    public function update(Request $request, Place $place): RedirectResponse
    {
        $request->validate([
            'category_id' => 'required|exists:place_categories,id',
            'title_en' => 'required|string|max:200',
            'title_ar' => 'nullable|string|max:200',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $imagePath = $place->raw_image;
        if ($request->hasFile('image')) {
            if ($imagePath) {
                Helpers::delete('places/' . $imagePath);
            }
            $imagePath = Helpers::upload('places/', 'png', $request->file('image'));
        }

        $place->update([
            'category_id' => $request->category_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'image' => $imagePath,
            'is_active' => $request->has('is_active'),
            'is_featured' => $request->has('is_featured'),
        ]);

        // Update English translation
        PlaceTranslation::updateOrCreate(
            ['place_id' => $place->id, 'locale' => 'en'],
            ['title' => $request->title_en, 'description' => $request->description_en]
        );

        // Update Arabic translation
        if ($request->title_ar) {
            PlaceTranslation::updateOrCreate(
                ['place_id' => $place->id, 'locale' => 'ar'],
                ['title' => $request->title_ar, 'description' => $request->description_ar]
            );
        }

        \Toastr::success(translate('messages.place_updated_successfully'));
        return redirect()->route('admin.places.index');
    }

    public function destroy(Place $place): RedirectResponse
    {
        if ($place->raw_image) {
            Helpers::delete('places/' . $place->raw_image);
        }
        
        $place->delete();
        
        \Toastr::success(translate('messages.place_deleted_successfully'));
        return redirect()->route('admin.places.index');
    }

    public function toggleStatus(Place $place): RedirectResponse
    {
        $place->update(['is_active' => !$place->is_active]);
        
        \Toastr::success(translate('messages.status_updated'));
        return back();
    }

    public function toggleFeatured(Place $place): RedirectResponse
    {
        $place->update(['is_featured' => !$place->is_featured]);
        
        \Toastr::success(translate('messages.featured_status_updated'));
        return back();
    }
}
