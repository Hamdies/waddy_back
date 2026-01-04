<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceVote;
use Modules\PlacesToVisit\Services\LeaderboardService;

class LeaderboardController extends Controller
{
    public function __construct(
        protected LeaderboardService $leaderboardService
    ) {}

    public function index(Request $request): View
    {
        $period = $request->period ?? now()->format('Y-m');
        $availablePeriods = $this->leaderboardService->getAvailablePeriods();
        
        $topPlaces = $this->leaderboardService->getTopPlaces($period);

        // Get overall stats
        $stats = [
            'total_votes' => PlaceVote::where('period', $period)->count(),
            'total_places' => Place::active()->count(),
            'participating_places' => PlaceVote::where('period', $period)
                ->distinct('place_id')
                ->count('place_id'),
            'average_rating' => PlaceVote::where('period', $period)
                ->whereNotNull('rating')
                ->avg('rating'),
        ];

        return view('placestovisit::admin.leaderboard.index', compact(
            'topPlaces', 
            'period', 
            'availablePeriods',
            'stats'
        ));
    }

    public function votes(Request $request): View
    {
        $period = $request->period ?? now()->format('Y-m');
        
        $votes = PlaceVote::query()
            ->with(['place.translations', 'user'])
            ->where('period', $period)
            ->when($request->flagged, fn($q) => $q->where('is_flagged', true))
            ->when($request->place_id, fn($q, $placeId) => $q->where('place_id', $placeId))
            ->latest()
            ->paginate(config('default_pagination'));

        $places = Place::active()->with('translations')->get();
        $availablePeriods = $this->leaderboardService->getAvailablePeriods();

        return view('placestovisit::admin.leaderboard.votes', compact(
            'votes', 
            'period', 
            'places',
            'availablePeriods'
        ));
    }

    public function toggleFlag(PlaceVote $vote): RedirectResponse
    {
        $vote->update(['is_flagged' => !$vote->is_flagged]);
        
        \Toastr::success(translate('messages.vote_flag_updated'));
        return back();
    }

    public function deleteVote(PlaceVote $vote): RedirectResponse
    {
        $vote->delete();
        $this->leaderboardService->clearCache();
        
        \Toastr::success(translate('messages.vote_deleted'));
        return back();
    }

    public function clearCache(): RedirectResponse
    {
        $this->leaderboardService->clearCache();
        
        \Toastr::success(translate('messages.cache_cleared'));
        return back();
    }
}
