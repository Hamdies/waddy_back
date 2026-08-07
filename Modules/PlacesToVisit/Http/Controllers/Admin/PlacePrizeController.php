<?php

namespace Modules\PlacesToVisit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlacePrize;
use Modules\PlacesToVisit\Services\PrizeRedemptionService;

class PlacePrizeController extends Controller
{
    public function __construct(
        protected PrizeRedemptionService $redemptionService
    ) {}

    public function index(Request $request): View
    {
        $prizes = PlacePrize::query()
            ->with(['user:id,f_name,l_name,phone', 'place'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->period, fn($q, $period) => $q->where('period', $period))
            ->when($request->place_id, fn($q, $placeId) => $q->where('place_id', $placeId))
            ->when($request->search, fn($q, $search) => $q->where('code', 'like', '%' . str_replace(' ', '', $search) . '%'))
            ->latest('id')
            ->paginate(config('default_pagination'))
            ->withQueryString();

        $places = Place::orderBy('id')->get();
        $periods = PlacePrize::query()->select('period')->distinct()
            ->orderByDesc('period')->pluck('period');

        $counts = [
            'active' => PlacePrize::where('status', PlacePrize::STATUS_ACTIVE)->count(),
            'redeemed' => PlacePrize::where('status', PlacePrize::STATUS_REDEEMED)->count(),
            'expired' => PlacePrize::where('status', PlacePrize::STATUS_EXPIRED)->count(),
        ];

        return view('placestovisit::admin.prizes.index', compact('prizes', 'places', 'periods', 'counts'));
    }

    /**
     * Manual override for the cases the venue page can't cover — a phone-in,
     * a cashier who couldn't load the link, a disputed redemption.
     */
    public function redeem(PlacePrize $prize): RedirectResponse
    {
        if (!$prize->place) {
            \Toastr::error(translate('messages.prize_venue_missing'));
            return back();
        }

        $result = $this->redemptionService->redeem($prize->code, $prize->place, request()->ip());

        $result['success']
            ? \Toastr::success($result['message'])
            : \Toastr::warning($result['message']);

        return back();
    }
}
