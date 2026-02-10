<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\CentralLogics\Helpers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceCategory;
use Modules\PlacesToVisit\Entities\PlaceSubmission;
use Modules\PlacesToVisit\Entities\PlaceTranslation;
use Modules\PlacesToVisit\Services\PlaceXpService;

class PlaceSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $submissions = PlaceSubmission::query()
            ->with(['user:id,f_name,l_name,email', 'category'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->search, function ($q, $search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq
                      ->where('f_name', 'like', "%{$search}%")
                      ->orWhere('l_name', 'like', "%{$search}%")
                  );
            })
            ->latest()
            ->paginate(config('default_pagination'));

        return view('placestovisit::admin.submissions.index', compact('submissions'));
    }

    public function show(PlaceSubmission $submission): View
    {
        $submission->load(['user:id,f_name,l_name,email,phone', 'category']);
        $categories = PlaceCategory::active()->ordered()->get();

        return view('placestovisit::admin.submissions.show', compact('submission', 'categories'));
    }

    /**
     * Approve a submission — creates a real Place from it
     */
    public function approve(Request $request, PlaceSubmission $submission): RedirectResponse
    {
        if (!$submission->isPending()) {
            \Toastr::warning(translate('messages.submission_already_processed'));
            return back();
        }

        $request->validate([
            'category_id' => 'required|exists:place_categories,id',
        ]);

        // Create the place
        $imagePath = null;
        if ($submission->image) {
            // Copy submission image to places folder
            $imagePath = $submission->image;
        }

        $place = Place::create([
            'category_id' => $request->category_id ?? $submission->category_id,
            'latitude' => $submission->latitude,
            'longitude' => $submission->longitude,
            'address' => $submission->address,
            'phone' => $submission->phone,
            'image' => $imagePath,
            'is_active' => true,
            'is_featured' => false,
        ]);

        // Create translation
        PlaceTranslation::create([
            'place_id' => $place->id,
            'locale' => 'en',
            'title' => $submission->title,
            'description' => $submission->description,
        ]);

        // Update submission status
        $submission->update([
            'status' => 'approved',
            'approved_place_id' => $place->id,
            'admin_note' => $request->admin_note,
        ]);

        // Award XP to the user
        if ($submission->user) {
            PlaceXpService::awardSubmissionApprovedXp($submission->user, $submission->id);
        }

        \Toastr::success(translate('messages.submission_approved'));
        return redirect()->route('admin.places.submissions.index');
    }

    /**
     * Reject a submission
     */
    public function reject(Request $request, PlaceSubmission $submission): RedirectResponse
    {
        if (!$submission->isPending()) {
            \Toastr::warning(translate('messages.submission_already_processed'));
            return back();
        }

        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $submission->update([
            'status' => 'rejected',
            'admin_note' => $request->admin_note,
        ]);

        \Toastr::success(translate('messages.submission_rejected'));
        return redirect()->route('admin.places.submissions.index');
    }

    public function destroy(PlaceSubmission $submission): RedirectResponse
    {
        if ($submission->image) {
            Helpers::check_and_delete('place_submissions/', $submission->image);
        }

        $submission->delete();

        \Toastr::success(translate('messages.submission_deleted'));
        return redirect()->route('admin.places.submissions.index');
    }
}
