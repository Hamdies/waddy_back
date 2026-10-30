<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * MA-4: a store's licences, owner ID and payout account, as the store app's
 * Documents & verification screen shows them, and support's review of new
 * uploads.
 *
 * - The current document of a type is its newest verified row. A newer
 *   pending row is "in review"; the verified one stays valid meanwhile, so
 *   the store keeps receiving orders.
 * - Stores that registered before this had their tax number, expiry and
 *   certificate on `stores` (tin, tin_expire_date); that counts as a
 *   verified tax card until a newer one is approved.
 * - The payout account lives on `vendors` (bank_name, holder_name,
 *   account_no). A change is a pending `bank` row and only reaches the
 *   vendor when approved; payouts keep going to the old account until then.
 *   A store with no account yet sets one directly.
 * - Files are kept on the private disk: they include ID cards. Support opens
 *   them through the admin panel.
 */
class StoreDocumentService
{
    public const DISK = 'local';
    public const DIR = 'store-documents';

    /** The document types the store uploads, in screen order. */
    public const FILE_TYPES = ['commercial_register', 'tax_card', 'health_licence', 'national_id'];

    /** Within this many days of its expiry a document needs attention. */
    public const EXPIRING_DAYS = 30;

    /** What the store app shows, one entry per type (files first, then bank). */
    public static function overview(Store $store, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $rows = StoreDocument::where('store_id', $store->id)->orderByDesc('id')->get()->groupBy('type');
        $out = [];
        foreach (self::FILE_TYPES as $type) {
            $out[] = self::entry($type, $rows->get($type, collect())->all(), self::legacy($store, $type), $today);
        }
        $out[] = self::bankEntry($store, $rows->get('bank', collect())->all());
        return $out;
    }

    /**
     * One document type: [$rows] newest first. Pure apart from [$legacy],
     * so it is tested.
     */
    public static function entry(string $type, array $rows, ?array $legacy, Carbon $today): array
    {
        $current = null;
        $pending = null;
        $rejected = null;
        foreach ($rows as $row) {
            if ($row->status === 'verified') {
                $current = $row;
                break;
            }
            if ($row->status === 'pending' && !$pending) {
                $pending = $row;
            }
            if ($row->status === 'rejected' && !$rejected && !$pending) {
                $rejected = $row;
            }
        }
        $c = $current ? [
            'number' => $current->number,
            'issued_by' => $current->issued_by,
            'holder_name' => $current->holder_name,
            'expires_at' => $current->expires_at?->toDateString(),
        ] : $legacy;

        $daysLeft = !empty($c['expires_at']) ? (int) $today->diffInDays(Carbon::parse($c['expires_at'])->startOfDay(), false) : null;
        $state = match (true) {
            $pending !== null => 'review',
            $rejected !== null => 'rejected',
            $c === null => 'missing',
            $daysLeft !== null && $daysLeft < 0 => 'expired',
            $daysLeft !== null && $daysLeft <= self::EXPIRING_DAYS => 'expiring',
            default => 'verified',
        };

        return [
            'type' => $type,
            'state' => $state,
            'number' => $c['number'] ?? null,
            'issued_by' => $c['issued_by'] ?? null,
            'holder_name' => $c['holder_name'] ?? null,
            'expires_at' => $c['expires_at'] ?? null,
            'days_left' => $daysLeft,
            // A rejected or pending upload while an older verified one still counts.
            'has_current' => $c !== null,
            'note' => $rejected?->note,
            'submitted_at' => ($pending ?? $rejected)?->created_at?->toIso8601String(),
        ];
    }

    private static function legacy(Store $store, string $type): ?array
    {
        if ($type !== 'tax_card' || empty($store->tin)) {
            return null;
        }
        return ['number' => $store->tin, 'issued_by' => null, 'holder_name' => null,
            'expires_at' => $store->tin_expire_date ? Carbon::parse($store->tin_expire_date)->toDateString() : null];
    }

    private static function bankEntry(Store $store, array $rows): array
    {
        $vendor = $store->vendor;
        $pending = collect($rows)->firstWhere('status', 'pending');
        $latest = $rows[0] ?? null;
        $rejected = $latest && $latest->status === 'rejected' ? $latest : null;
        $has = $vendor && !empty($vendor->bank_name) && !empty($vendor->account_no);
        return [
            'type' => 'bank',
            'state' => $pending ? 'review' : ($rejected ? 'rejected' : ($has ? 'verified' : 'missing')),
            'bank_name' => $has ? $vendor->bank_name : null,
            'holder_name' => $has ? $vendor->holder_name : null,
            'account_last4' => $has ? self::last4($vendor->account_no) : null,
            'has_current' => $has,
            'pending_bank_name' => $pending?->details['bank_name'] ?? null,
            'pending_account_last4' => $pending ? self::last4($pending->details['account_no'] ?? '') : null,
            'note' => $rejected?->note,
            'submitted_at' => ($pending ?? $rejected)?->created_at?->toIso8601String(),
        ];
    }

    private static function last4(?string $account): string
    {
        $digits = preg_replace('/\s+/', '', (string) $account);
        return mb_substr($digits, -4);
    }

    /** A new file for review; replaces an earlier upload still waiting. */
    public static function upload(Store $store, string $type, UploadedFile $file): StoreDocument
    {
        $name = now()->toDateString() . '-' . uniqid() . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');
        Storage::disk(self::DISK)->putFileAs(self::DIR, $file, $name);
        self::dropPending($store, $type);
        return StoreDocument::create([
            'store_id' => $store->id,
            'type' => $type,
            'status' => 'pending',
            'file' => $name,
            'file_disk' => self::DISK,
        ]);
    }

    /**
     * A new payout account. Returns true when it was applied at once (the
     * store had none), false when it waits for review.
     */
    public static function requestBank(Store $store, Vendor $vendor, array $data): bool
    {
        $details = [
            'bank_name' => trim($data['bank_name']),
            'holder_name' => trim($data['holder_name']),
            'account_no' => preg_replace('/\s+/', ' ', trim($data['account_no'])),
            'branch' => isset($data['branch']) ? trim($data['branch']) : null,
        ];
        self::dropPending($store, 'bank');
        $first = empty($vendor->bank_name) || empty($vendor->account_no);
        $row = StoreDocument::create([
            'store_id' => $store->id,
            'type' => 'bank',
            'status' => $first ? 'verified' : 'pending',
            'details' => $details,
            'reviewed_at' => $first ? now() : null,
        ]);
        if ($first) {
            self::applyBank($vendor, $row);
        }
        return $first;
    }

    private static function dropPending(Store $store, string $type): void
    {
        $old = StoreDocument::where(['store_id' => $store->id, 'type' => $type, 'status' => 'pending'])->get();
        foreach ($old as $row) {
            if ($row->file) {
                Storage::disk($row->file_disk ?: self::DISK)->delete(self::DIR . '/' . $row->file);
            }
            $row->delete();
        }
    }

    private static function applyBank(Vendor $vendor, StoreDocument $row): void
    {
        $d = $row->details ?? [];
        $vendor->bank_name = $d['bank_name'] ?? $vendor->bank_name;
        $vendor->holder_name = $d['holder_name'] ?? $vendor->holder_name;
        $vendor->account_no = $d['account_no'] ?? $vendor->account_no;
        if (!empty($d['branch'])) {
            $vendor->branch = $d['branch'];
        }
        $vendor->save();
    }

    /** Support approved it, with what it read off the file. */
    public static function approve(StoreDocument $doc, int $adminId, array $fields = []): void
    {
        $doc->fill(array_intersect_key($fields, array_flip(['number', 'issued_by', 'holder_name', 'expires_at'])));
        $doc->status = 'verified';
        $doc->note = null;
        $doc->reviewed_by = $adminId;
        $doc->reviewed_at = now();
        $doc->save();
        if ($doc->type === 'bank' && ($vendor = $doc->store?->vendor)) {
            self::applyBank($vendor, $doc);
        }
        self::notify($doc, true);
    }

    public static function reject(StoreDocument $doc, int $adminId, string $note): void
    {
        $doc->status = 'rejected';
        $doc->note = mb_substr($note, 0, 500);
        $doc->reviewed_by = $adminId;
        $doc->reviewed_at = now();
        $doc->save();
        self::notify($doc, false);
    }

    /** Names the store app also uses (en, ar). */
    public static function label(string $type, ?string $moduleType = null): array
    {
        return match ($type) {
            'commercial_register' => ['Commercial register', 'السجل التجاري'],
            'tax_card' => ['Tax card', 'البطاقة الضريبية'],
            'health_licence' => $moduleType === 'food' ? ['Health licence', 'الرخصة الصحية'] : ['Food safety licence', 'رخصة سلامة الغذاء'],
            'national_id' => ['National ID', 'بطاقة الرقم القومي'],
            'bank' => ['Payout account', 'حساب استلام المدفوعات'],
            default => [$type, $type],
        };
    }

    private static function notify(StoreDocument $doc, bool $ok): void
    {
        $store = $doc->store;
        if (!$store) {
            return;
        }
        $label = self::label($doc->type, $store->module?->module_type);
        try {
            StorePushService::sendLocalized($store, fn ($lang) => [
                'title' => $lang === 'ar'
                    ? ($ok ? "تم التحقق: {$label[1]}" : "مطلوب ملف جديد: {$label[1]}")
                    : ($ok ? "{$label[0]} verified" : "{$label[0]}: please upload again"),
                'description' => $ok
                    ? ($lang === 'ar' ? 'تم قبول الملف الجديد.' : 'Your new file was approved.')
                    : ($doc->note ?: ($lang === 'ar' ? 'لم نتمكن من قبول الملف.' : 'We could not accept the file.')),
                'order_id' => '',
                'image' => '',
                'type' => 'document',
            ]);
        } catch (\Throwable $e) {
            Log::warning("document push failed for {$doc->id}: " . $e->getMessage());
        }
    }
}
