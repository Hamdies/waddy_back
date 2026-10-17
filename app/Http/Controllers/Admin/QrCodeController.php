<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\QrCode;
use App\Models\QrCodeScan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin › Business settings › QR code generator. Codes are drawn in the
 * browser by a local copy of qr-code-styling, so nothing leaves the panel
 * and no external generator site is needed.
 *
 * A tracked code encodes our short link (/q/{code}) instead of the
 * destination, so scans are counted and the destination can change later.
 */
class QrCodeController extends Controller
{
    /** The generator. `?edit={id}` opens a tracked code with its saved look. */
    public function index(Request $request): View
    {
        $editing = $request->filled('edit') ? QrCode::find($request->integer('edit')) : null;

        return view('admin-views.qr-code.index', [
            'presets' => $this->presets(),
            'logo' => $this->logoDataUri(),
            'editing' => $editing ? $this->toJson($editing) : null,
            'trackedCount' => QrCode::count(),
            'shortBase' => url('/q'),
        ]);
    }

    public function tracked(Request $request): View
    {
        $codes = QrCode::query()
            ->when($request->search, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('target_url', 'like', "%{$search}%")
                ->orWhere('code', $search)))
            ->withCount([
                'scans as week_scans' => fn ($q) => $q->where('scanned_at', '>=', now()->subDays(7)),
                'scans as today_scans' => fn ($q) => $q->where('scanned_at', '>=', now()->startOfDay()),
            ])
            ->addSelect(['unique_scans' => QrCodeScan::selectRaw('count(distinct visitor_hash)')
                ->whereColumn('qr_code_id', 'qr_codes.id')])
            ->latest()
            ->paginate(config('default_pagination'));

        return view('admin-views.qr-code.tracked', compact('codes'));
    }

    /** Scans for one code: totals, last 30 days by day, and devices. */
    public function show(QrCode $qrCode): View
    {
        $scans = $qrCode->scans();
        $since = now()->subDays(29)->startOfDay();

        $byDay = (clone $scans)->where('scanned_at', '>=', $since)
            ->selectRaw('DATE(scanned_at) as day, count(*) as total')
            ->groupBy('day')->pluck('total', 'day');
        $days = [];
        for ($d = $since->copy(); $d->lte(now()); $d->addDay()) {
            $days[$d->toDateString()] = (int) ($byDay[$d->toDateString()] ?? 0);
        }

        $devices = (clone $scans)->selectRaw('device, count(*) as total')->groupBy('device')->pluck('total', 'device');

        return view('admin-views.qr-code.show', [
            'qr' => $qrCode,
            'days' => $days,
            'devices' => $devices,
            'unique' => (clone $scans)->distinct()->count('visitor_hash'),
            'week' => (clone $scans)->where('scanned_at', '>=', now()->subDays(7))->count(),
            'today' => (clone $scans)->where('scanned_at', '>=', now()->startOfDay())->count(),
        ]);
    }

    /** Called by the generator (AJAX) when a tracked code is first saved or downloaded. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedCode($request);
        $qr = QrCode::create($data + [
            'code' => QrCode::newCode(),
            'created_by' => auth('admin')->id(),
        ]);
        return response()->json($this->toJson($qr), 201);
    }

    /** New name, destination or look. The short link, and every printed copy, stays the same. */
    public function update(Request $request, QrCode $qrCode): JsonResponse
    {
        $qrCode->update($this->validatedCode($request));
        return response()->json($this->toJson($qrCode->fresh()));
    }

    public function toggle(QrCode $qrCode): RedirectResponse
    {
        $qrCode->update(['is_active' => !$qrCode->is_active]);
        \Toastr::success(translate('messages.status_updated'));
        return back();
    }

    public function destroy(QrCode $qrCode): RedirectResponse
    {
        $qrCode->delete();
        \Toastr::success(translate('messages.qr_deleted'));
        return redirect()->route('admin.business-settings.qr-code.tracked');
    }

    private function validatedCode(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => ['required', Rule::in(QrCode::TYPES)],
            // http(s) only: the redirect must never become a javascript: or data: link.
            'target_url' => ['required', 'string', 'max:2000', 'url', 'regex:/^https?:\/\//i'],
            'style' => 'nullable|array',
        ], [
            'target_url.*' => translate('messages.qr_target_invalid'),
        ]);

        return [
            'name' => $request->name,
            'type' => $request->type,
            'target_url' => $request->target_url,
            'style' => $request->input('style'),
        ];
    }

    private function toJson(QrCode $qr): array
    {
        return [
            'id' => $qr->id,
            'code' => $qr->code,
            'name' => $qr->name,
            'type' => $qr->type,
            'target_url' => $qr->target_url,
            'style' => $qr->style,
            'short_url' => $qr->shortUrl(),
            'scans' => $qr->scans_count,
            'is_active' => $qr->is_active,
            'update_url' => route('admin.business-settings.qr-code.update', $qr->id),
            'stats_url' => route('admin.business-settings.qr-code.show', $qr->id),
        ];
    }

    /** Our own links, offered as one-tap fills. Empty settings are skipped. */
    private function presets(): array
    {
        $settings = BusinessSetting::whereIn('key', [
            'app_url_android', 'app_url_ios',
            'app_url_android_store', 'app_url_ios_store',
            'app_url_android_deliveryman', 'app_url_ios_deliveryman',
        ])->pluck('value', 'key');

        $presets = [
            ['group' => 'website', 'label' => 'qr_preset_home', 'url' => route('home')],
            ['group' => 'website', 'label' => 'qr_preset_vet_apply', 'url' => route('vet-clinic.create')],
            ['group' => 'website', 'label' => 'qr_preset_vendor_apply', 'url' => route('restaurant.create')],
            ['group' => 'website', 'label' => 'qr_preset_deliveryman_apply', 'url' => route('deliveryman.create')],
        ];
        foreach ([
            'app_url_android' => 'qr_preset_user_android',
            'app_url_ios' => 'qr_preset_user_ios',
            'app_url_android_store' => 'qr_preset_store_android',
            'app_url_ios_store' => 'qr_preset_store_ios',
            'app_url_android_deliveryman' => 'qr_preset_dm_android',
            'app_url_ios_deliveryman' => 'qr_preset_dm_ios',
        ] as $key => $label) {
            $url = trim((string) ($settings[$key] ?? ''));
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $presets[] = ['group' => 'apps', 'label' => $label, 'url' => $url];
            }
        }

        return $presets;
    }

    /**
     * The business logo inlined as a data URI. Loading it by URL (often S3)
     * would taint the canvas and block the PNG download.
     */
    private function logoDataUri(): ?string
    {
        $logo = BusinessSetting::where('key', 'logo')->first();
        if (!$logo?->value) {
            return null;
        }
        $disk = ($logo->storage[0]->value ?? 'public') === 's3' ? 's3' : 'public';
        $path = 'business/' . $logo->value;

        try {
            if (!Storage::disk($disk)->exists($path)) {
                return null;
            }
            $bytes = Storage::disk($disk)->get($path);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png';
            if (!str_starts_with($mime, 'image/') || strlen($bytes) > 2 * 1024 * 1024) {
                return null;
            }
            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
