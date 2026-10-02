<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\PlaceCategory;
use App\CentralLogics\Helpers;

class PlaceCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = PlaceCategory::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->ordered()
            ->paginate(config('default_pagination'));

        return view('placestovisit::admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('placestovisit::admin.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'priority' => 'nullable|integer|min:0',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = Helpers::upload('place-category/', 'png', $request->file('image'));
        }

        PlaceCategory::create([
            'name' => $request->name,
            'image' => $imagePath,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->has('is_active'),
        ]);

        \Toastr::success(translate('messages.category_created_successfully'));
        return redirect()->route('admin.places.categories.index');
    }

    public function edit(PlaceCategory $category): View
    {
        return view('placestovisit::admin.categories.edit', compact('category'));
    }

    public function update(Request $request, PlaceCategory $category): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'priority' => 'nullable|integer|min:0',
        ]);

        $imagePath = $category->image;
        if ($request->hasFile('image')) {
            if ($imagePath) {
                Helpers::delete('place-category/' . $imagePath);
            }
            $imagePath = Helpers::upload('place-category/', 'png', $request->file('image'));
        }

        $category->update([
            'name' => $request->name,
            'image' => $imagePath,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->has('is_active'),
        ]);

        \Toastr::success(translate('messages.category_updated_successfully'));
        return redirect()->route('admin.places.categories.index');
    }

    public function destroy(PlaceCategory $category): RedirectResponse
    {
        if ($category->image) {
            Helpers::delete('place-category/' . $category->image);
        }
        
        $category->delete();
        
        \Toastr::success(translate('messages.category_deleted_successfully'));
        return redirect()->route('admin.places.categories.index');
    }

    public function toggleStatus(PlaceCategory $category): RedirectResponse
    {
        $category->update(['is_active' => !$category->is_active]);
        
        \Toastr::success(translate('messages.status_updated'));
        return back();
    }
}
