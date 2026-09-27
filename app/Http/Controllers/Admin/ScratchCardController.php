<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\ScratchBatch;
use App\Models\ScratchRange;
use App\Models\Zone;
use App\Services\ScratchCardService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

/**
 * Admin side of the physical scratch cards (SC-02, SC-09, SC-14, SC-15).
 */
class ScratchCardController extends Controller
{
    public function index()
    {
        $batches = ScratchBatch::with('zone')
            ->withCount([
                'codes as winners_count',
                'codes as reached_count' => fn ($q) => $q->whereNotNull('user_id'),
                'codes as used_count' => fn ($q) => $q->whereNotNull('used_at'),
            ])
            ->latest()
            ->paginate(20);

        return view('admin-views.scratch-cards.index', [
            'batches' => $batches,
            'zones' => Zone::orderBy('name')->get(['id', 'name']),
            'enabled' => ScratchCardService::enabled(),
            'cap' => ScratchCardService::accountCap(),
            'capDays' => ScratchCardService::capDays(),
        ]);
    }

    public function store(Request $request)
    {
        if (!ScratchCardService::enabled()) {
            Toastr::error('The scratch card program is switched off. Turn it on to create a batch.');
            return back();
        }

        $data = $request->validate([
            'name' => 'required|string|max:50',
            'quantity' => 'required|integer|min:1|max:20000',
            'free_delivery_winners' => 'required|integer|min:0',
            'discount_winners' => 'required|integer|min:0',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_min_order' => 'nullable|numeric|min:0',
            'use_before' => 'required|date|after_or_equal:today',
            'zone_id' => 'nullable|integer|exists:zones,id',
        ]);
        $data['discount_value'] = (float) ($data['discount_value'] ?? 0);
        $data['discount_min_order'] = (float) ($data['discount_min_order'] ?? 0);
        $data['zone_id'] = $data['zone_id'] ?? null;

        if ($data['free_delivery_winners'] + $data['discount_winners'] > $data['quantity']) {
            Toastr::error('Winners can\'t be more than the number of cards.');
            return back()->withInput();
        }
        if ($data['discount_winners'] > 0 && $data['discount_value'] <= 0) {
            Toastr::error('Set the discount value for discount winners.');
            return back()->withInput();
        }

        $batch = ScratchCardService::generate($data);

        Toastr::success("Batch {$batch->name} created. It stays off until you switch it on.");
        return redirect()->route('admin.users.customer.scratch.show', $batch->id);
    }

    public function show($id)
    {
        $batch = ScratchBatch::with('zone')->findOrFail($id);

        return view('admin-views.scratch-cards.show', [
            'batch' => $batch,
            'report' => ScratchCardService::rangeReport($batch),
            'zones' => Zone::orderBy('name')->get(['id', 'name']),
            'winners' => $batch->codes()->count(),
            'reached' => $batch->codes()->whereNotNull('user_id')->count(),
            'used' => $batch->codes()->whereNotNull('used_at')->count(),
            'minHolderWinners' => ScratchCardService::REPORT_MIN_HOLDER_WINNERS,
        ]);
    }

    /**
     * Per-batch on/off. Turning a batch OFF is always allowed (a lost box);
     * turning one ON needs the program switch on and a date still ahead.
     */
    public function toggle($id)
    {
        $batch = ScratchBatch::findOrFail($id);

        if (!$batch->active) {
            if (!ScratchCardService::enabled()) {
                Toastr::error('The scratch card program is switched off.');
                return back();
            }
            if ($batch->isExpired()) {
                Toastr::error('This batch is past its use-before date. Extend it first.');
                return back();
            }
        }

        $batch->update(['active' => !$batch->active]);
        Toastr::success($batch->active ? "Batch {$batch->name} is on." : "Batch {$batch->name} is off. Its unused codes are refused.");
        return back();
    }

    /**
     * Push the use-before date later, never earlier: it is printed on the
     * cards. Coupons already minted from this batch get the new date too.
     */
    public function extend(Request $request, $id)
    {
        $batch = ScratchBatch::findOrFail($id);
        $request->validate(['use_before' => 'required|date|after_or_equal:' . $batch->use_before->toDateString()]);

        $batch->update(['use_before' => $request->use_before]);
        Coupon::whereIn('id', $batch->codes()->whereNotNull('coupon_id')->pluck('coupon_id'))
            ->update(['expire_date' => $batch->use_before->toDateString()]);

        Toastr::success('Use-before date extended.');
        return back();
    }

    /** The printer file: every card, losers included, in card-number order. */
    public function export($id)
    {
        $batch = ScratchBatch::findOrFail($id);
        $filename = 'scratch-cards-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $batch->name) . '.csv';

        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows the Arabic column
            fputcsv($out, ['card_no', 'batch', 'result', 'code', 'text_en', 'text_ar', 'use_before']);
            foreach (ScratchCardService::printRows($batch) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function rangeStore(Request $request, $id)
    {
        $batch = ScratchBatch::findOrFail($id);
        $data = $request->validate([
            'from_no' => 'required|integer|min:1|max:' . $batch->quantity,
            'to_no' => 'required|integer|gte:from_no|max:' . $batch->quantity,
            'holder_type' => 'required|in:rider,store',
            'holder_name' => 'required|string|max:100',
            'zone_id' => 'nullable|integer|exists:zones,id',
            'handed_at' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $overlaps = $batch->ranges()
            ->where('from_no', '<=', $data['to_no'])
            ->where('to_no', '>=', $data['from_no'])
            ->exists();
        if ($overlaps) {
            Toastr::error('Those card numbers overlap a range already logged.');
            return back()->withInput();
        }

        $data['zone_id'] = $data['zone_id'] ?? $batch->zone_id;
        $batch->ranges()->create($data);
        Toastr::success('Range logged.');
        return back();
    }

    public function rangeDelete($id, $rangeId)
    {
        ScratchRange::where('batch_id', $id)->findOrFail($rangeId)->delete();
        Toastr::success('Range removed.');
        return back();
    }

    public function settingsUpdate(Request $request)
    {
        $data = $request->validate([
            'account_cap' => 'required|integer|min:1|max:100',
            'cap_days' => 'required|integer|min:1|max:365',
        ]);
        ScratchCardService::saveSettings($request->has('status'), $data['account_cap'], $data['cap_days']);

        Toastr::success(translate('messages.settings_updated_successfully'));
        return back();
    }
}
