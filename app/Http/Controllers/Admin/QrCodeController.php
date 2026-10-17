<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

/**
 * Admin › Business settings › QR code generator. Codes are drawn in the
 * browser by a local copy of qr-code-styling, so nothing leaves the panel
 * and no external generator site is needed.
 */
class QrCodeController extends Controller
{
    public function index(): View
    {
        return view('admin-views.qr-code.index', [
            'presets' => $this->presets(),
            'logo' => $this->logoDataUri(),
        ]);
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
