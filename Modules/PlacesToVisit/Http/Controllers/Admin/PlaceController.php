<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceCategory;
use Modules\PlacesToVisit\Entities\PlaceTag;
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
            ->withCount('favorites')
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
        $tags = PlaceTag::active()->get();
        return view('placestovisit::admin.places.create', compact('categories', 'tags'));
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
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'instagram' => 'nullable|string|max:255',
            'opening_hours' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gallery.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:place_tags,id',
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
            'phone' => $request->phone,
            'website' => $request->website,
            'instagram' => $request->instagram,
            'opening_hours' => $request->opening_hours,
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

        // Handle gallery uploads
        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $index => $file) {
                $galleryPath = Helpers::upload('places/', 'png', $file);
                $place->images()->create([
                    'image' => $galleryPath,
                    'sort_order' => $index,
                    'is_primary' => $index === 0 && !$imagePath,
                ]);
            }
        }

        // Attach tags
        if ($request->tags) {
            $place->tags()->sync($request->tags);
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
        $place->load(['translations', 'images', 'tags']);
        $categories = PlaceCategory::active()->ordered()->get();
        $tags = PlaceTag::active()->get();
        
        $translations = $place->translations->keyBy('locale');
        $selectedTags = $place->tags->pluck('id')->toArray();
        
        return view('placestovisit::admin.places.edit', compact('place', 'categories', 'tags', 'translations', 'selectedTags'));
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
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'instagram' => 'nullable|string|max:255',
            'opening_hours' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gallery.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:place_tags,id',
        ]);

        $imagePath = $place->raw_image;
        if ($request->hasFile('image')) {
            if ($imagePath) {
                Helpers::check_and_delete('places/', $imagePath);
            }
            $imagePath = Helpers::upload('places/', 'png', $request->file('image'));
        }

        $place->update([
            'category_id' => $request->category_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'phone' => $request->phone,
            'website' => $request->website,
            'instagram' => $request->instagram,
            'opening_hours' => $request->opening_hours,
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

        // Handle gallery uploads
        if ($request->hasFile('gallery')) {
            $maxOrder = $place->images()->max('sort_order') ?? -1;
            foreach ($request->file('gallery') as $index => $file) {
                $galleryPath = Helpers::upload('places/', 'png', $file);
                $place->images()->create([
                    'image' => $galleryPath,
                    'sort_order' => $maxOrder + $index + 1,
                    'is_primary' => false,
                ]);
            }
        }

        // Sync tags
        $place->tags()->sync($request->tags ?? []);

        \Toastr::success(translate('messages.place_updated_successfully'));
        return redirect()->route('admin.places.index');
    }

    public function destroy(Place $place): RedirectResponse
    {
        if ($place->raw_image) {
            Helpers::check_and_delete('places/', $place->raw_image);
        }
        
        // Delete gallery images
        foreach ($place->images as $img) {
            Helpers::check_and_delete('places/', $img->image);
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
