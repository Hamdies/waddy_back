<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\CentralLogics\Helpers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceOffer;

class PlaceOfferController extends Controller
{
    public function index(Request $request): View
    {
        $offers = PlaceOffer::query()
            ->with('place.translations')
            ->when($request->search, function ($q, $search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('place.translations', fn($pq) => $pq->where('title', 'like', "%{$search}%"));
            })
            ->when($request->place_id, fn($q, $placeId) => $q->where('place_id', $placeId))
            ->when($request->status === 'active', fn($q) => $q->active())
            ->latest()
            ->paginate(config('default_pagination'));

        $places = Place::active()->with('translations')->get();

        return view('placestovisit::admin.offers.index', compact('offers', 'places'));
    }

    public function create(): View
    {
        $places = Place::active()->with('translations')->get();
        return view('placestovisit::admin.offers.create', compact('places'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'place_id' => 'required|exists:places,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = Helpers::upload('place_offers/', 'png', $request->file('image'));
        }

        PlaceOffer::create([
            'place_id' => $request->place_id,
            'title' => $request->title,
            'description' => $request->description,
            'image' => $imagePath,
            'discount_percent' => $request->discount_percent,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => $request->has('is_active'),
        ]);

        \Toastr::success(translate('messages.offer_created_successfully'));
        return redirect()->route('admin.places.offers.index');
    }

    public function edit(PlaceOffer $offer): View
    {
        $places = Place::active()->with('translations')->get();
        return view('placestovisit::admin.offers.edit', compact('offer', 'places'));
    }

    public function update(Request $request, PlaceOffer $offer): RedirectResponse
    {
        $request->validate([
            'place_id' => 'required|exists:places,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $imagePath = $offer->image;
        if ($request->hasFile('image')) {
            if ($imagePath) {
                Helpers::check_and_delete('place_offers/', $imagePath);
            }
            $imagePath = Helpers::upload('place_offers/', 'png', $request->file('image'));
        }

        $offer->update([
            'place_id' => $request->place_id,
            'title' => $request->title,
            'description' => $request->description,
            'image' => $imagePath,
            'discount_percent' => $request->discount_percent,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => $request->has('is_active'),
        ]);

        \Toastr::success(translate('messages.offer_updated_successfully'));
        return redirect()->route('admin.places.offers.index');
    }

    public function destroy(PlaceOffer $offer): RedirectResponse
    {
        if ($offer->image) {
            Helpers::check_and_delete('place_offers/', $offer->image);
        }

        $offer->delete();

        \Toastr::success(translate('messages.offer_deleted_successfully'));
        return redirect()->route('admin.places.offers.index');
    }

    public function toggleStatus(PlaceOffer $offer): RedirectResponse
    {
        $offer->update(['is_active' => !$offer->is_active]);

        \Toastr::success(translate('messages.status_updated'));
        return back();
    }
}
