<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\CentralLogics\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\PlaceSubmission;
use Modules\PlacesToVisit\Entities\PlaceCategory;

class PlaceSubmissionController extends Controller
{
    /**
     * List user's submissions
     * GET /api/v1/places/submissions
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) ($request->page ?? $request->offset ?? 1);
        $submissions = PlaceSubmission::byUser(auth()->id())
            ->with('category')
            ->latest()
            ->paginate($request->per_page ?? 15, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => collect($submissions->items())->map(fn($sub) => [
                'id' => $sub->id,
                'title' => $sub->title,
                'description' => $sub->description,
                'image' => $sub->image_url,
                'category' => $sub->category?->localized_name,
                'latitude' => $sub->latitude,
                'longitude' => $sub->longitude,
                'address' => $sub->address,
                'status' => $sub->status,
                'admin_note' => $sub->admin_note,
                'created_at' => $sub->created_at,
            ]),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    /**
     * Submit a new place suggestion
     * POST /api/v1/places/submissions
     */
    public function store(Request $request): JsonResponse
    {
        // Older app builds send the place name as `name`
        if (!$request->filled('title') && $request->filled('name')) {
            $request->merge(['title' => $request->name]);
        }

        $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'category_id' => 'nullable|exists:place_categories,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:' . config('placestovisit.submissions.image_max_size', 2048),
        ]);

        $userId = auth()->id();

        // Check pending limit
        $maxPending = config('placestovisit.submissions.max_pending_per_user', 5);
        $pendingCount = PlaceSubmission::byUser($userId)->pending()->count();

        if ($pendingCount >= $maxPending) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.max_submissions_reached'),
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = Helpers::upload('place_submissions/', 'png', $request->file('image'));
        }

        $submission = PlaceSubmission::create([
            'user_id' => $userId,
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'image' => $imagePath,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'phone' => $request->phone,
            'website' => $request->website,
            'instagram' => $request->instagram,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('messages.submission_received'),
            'data' => [
                'id' => $submission->id,
                'status' => $submission->status,
            ],
        ], 201);
    }

    /**
     * Get submission detail
     * GET /api/v1/places/submissions/{id}
     */
    public function show(PlaceSubmission $submission): JsonResponse
    {
        // Only allow viewing own submissions
        if ($submission->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.unauthorized'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $submission->id,
                'title' => $submission->title,
                'description' => $submission->description,
                'image' => $submission->image_url,
                'category' => $submission->category?->localized_name,
                'latitude' => $submission->latitude,
                'longitude' => $submission->longitude,
                'address' => $submission->address,
                'phone' => $submission->phone,
                'website' => $submission->website,
                'instagram' => $submission->instagram,
                'status' => $submission->status,
                'admin_note' => $submission->admin_note,
                'approved_place_id' => $submission->approved_place_id,
                'created_at' => $submission->created_at,
            ],
        ]);
    }
}
