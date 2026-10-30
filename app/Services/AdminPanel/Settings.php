<?php

namespace App\Services\AdminPanel;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\PlacesToVisit\Services\LeaderboardService;

/**
 * The two settings pages: platform settings (business_settings and module
 * switches) and the Spots race settings (config overrides, see
 * PlacesToVisitServiceProvider::applyRaceOverrides).
 */
class Settings
{
    public const RACE_KEY = 'spots_race_settings';

    /** Race setting => config path under placestovisit. */
    public const RACE = [
        'prizeEnabled' => 'prize.enabled',
        'winnersPerWeek' => 'prize.winners_per_week',
        'validityDays' => 'prize.validity_days',
        'valueCap' => 'prize.value_cap',
        'cooldownDays' => 'prize.winner_cooldown_days',
        'maxEntrants' => 'draw.max_entrants',
        'minVotes' => 'min_votes_for_leaderboard',
        'leaderboardLimit' => 'leaderboard_limit',
        'finalHoursGap' => 'race.final_hours_gap',
        'leadPushCooldown' => 'race.lead_push_cooldown_minutes',
        'reportThreshold' => 'report_auto_flag_threshold',
        'maxPendingSubs' => 'submissions.max_pending_per_user',
        'xpVote' => 'xp.vote',
        'xpReview' => 'xp.review',
        'xpPhoto' => 'xp.photo_review',
        'xpSubmission' => 'xp.submission_approved',
    ];

    private const MODULE_TOGGLES = ['foodOn' => 'food', 'groceryOn' => 'grocery', 'petsOn' => 'pets', 'spotsOn' => 'spots'];

    public static function groups(): array
    {
        $L = fn ($en, $ar) => [$en, $ar];
        return [
            ['icon' => 'settings', 'title' => $L('General', 'عام'), 'fields' => [
                ['key' => 'appName', 'label' => $L('App name', 'اسم التطبيق'), 'required' => true],
                ['key' => 'supportPhone', 'label' => $L('Support phone', 'هاتف الدعم')],
                ['key' => 'supportEmail', 'label' => $L('Support email', 'بريد الدعم'), 'type' => 'email'],
                ['key' => 'minVersion', 'label' => $L('Minimum app version (Android)', 'أقل إصدار (أندرويد)')],
                ['key' => 'minVersionIos', 'label' => $L('Minimum app version (iOS)', 'أقل إصدار (iOS)')],
            ]],
            ['icon' => 'all', 'title' => $L('Modules in the app', 'الأقسام في التطبيق'), 'fields' => [
                ['key' => 'foodOn', 'label' => $L('Food', 'طعام'), 'type' => 'toggle'],
                ['key' => 'groceryOn', 'label' => $L('Grocery', 'بقالة'), 'type' => 'toggle'],
                ['key' => 'petsOn', 'label' => $L('Pets', 'حيوانات أليفة'), 'type' => 'toggle'],
                ['key' => 'spotsOn', 'label' => $L('Waddy Spots', 'أماكن ودي'), 'type' => 'toggle'],
            ]],
            ['icon' => 'truck', 'title' => $L('Delivery & fees', 'التوصيل والرسوم'), 'fields' => [
                ['key' => 'freeOver', 'label' => $L('Free delivery over (EGP, empty = off)', 'توصيل مجاني للطلبات فوق'), 'type' => 'number'],
                ['key' => 'serviceFee', 'label' => $L('Service fee per order (EGP, empty = off)', 'رسوم الخدمة لكل طلب'), 'type' => 'number'],
                ['key' => 'commission', 'label' => $L('Default store commission %', 'عمولة المتجر الافتراضية'), 'type' => 'number'],
                ['key' => 'dmCommission', 'label' => $L('Commission on delivery fee %', 'العمولة على رسوم التوصيل'), 'type' => 'number'],
            ]],
            ['icon' => 'orders', 'title' => $L('Ordering', 'الطلبات'), 'fields' => [
                ['key' => 'cod', 'label' => $L('Cash on delivery', 'الدفع عند الاستلام'), 'type' => 'toggle'],
                ['key' => 'scheduled', 'label' => $L('Scheduled orders', 'الطلبات المجدولة'), 'type' => 'toggle'],
                ['key' => 'tipping', 'label' => $L('Courier tipping', 'إكرامية المندوب'), 'type' => 'toggle'],
                ['key' => 'refundsOn', 'label' => $L('Customer complaints and refunds', 'شكاوى العملاء والاسترداد'), 'type' => 'toggle'],
                ['key' => 'refundWindow', 'label' => $L('Complaint window after delivery (minutes)', 'مدة الشكوى بعد التوصيل (دقائق)'), 'type' => 'number'],
                ['key' => 'maintenance', 'label' => $L('Maintenance mode', 'وضع الصيانة'), 'type' => 'toggle'],
            ]],
        ];
    }

    public static function raceGroups(): array
    {
        $L = fn ($en, $ar) => [$en, $ar];
        $n = fn ($key, $en, $ar) => ['key' => $key, 'label' => $L($en, $ar), 'type' => 'number'];
        return [
            ['icon' => 'spotPrizes', 'title' => $L('Prize draw', 'السحب'), 'fields' => [
                ['key' => 'prizeEnabled', 'label' => $L('Weekly draw enabled', 'تفعيل السحب الأسبوعي'), 'type' => 'toggle'],
                $n('winnersPerWeek', 'Winners per week', 'الفائزون أسبوعيًا'),
                $n('validityDays', 'Voucher validity (days)', 'صلاحية القسيمة (أيام)'),
                $n('valueCap', 'Default voucher cap (EGP)', 'حد القسيمة الافتراضي'),
                $n('cooldownDays', 'Winner cooldown (days)', 'فترة انتظار الفائز (أيام)'),
                $n('maxEntrants', 'Entrants stored for the claw', 'المشاركون المحفوظون للآلة'),
            ]],
            ['icon' => 'spotWinners', 'title' => $L('Race', 'السباق'), 'fields' => [
                $n('minVotes', 'Min. votes to appear on board', 'أقل أصوات للظهور'),
                $n('leaderboardLimit', 'Leaderboard size', 'حجم اللوحة'),
                $n('finalHoursGap', 'Final-hours push gap', 'فارق تنبيه الساعات الأخيرة'),
                $n('leadPushCooldown', 'Lead-change push cooldown (min)', 'فترة تنبيه تغيير الصدارة (د)'),
                $n('reportThreshold', 'Reports to auto-flag a review', 'بلاغات الإخفاء التلقائي'),
                $n('maxPendingSubs', 'Max pending submissions per user', 'أقصى اقتراحات معلقة للمستخدم'),
            ]],
            ['icon' => 'trend', 'title' => $L('XP', 'النقاط'), 'fields' => [
                $n('xpVote', 'Weekly vote', 'التصويت الأسبوعي'),
                $n('xpReview', 'Review', 'تقييم'),
                $n('xpPhoto', 'Photo on review', 'صورة مع التقييم'),
                $n('xpSubmission', 'Submission approved', 'قبول اقتراح'),
            ]],
        ];
    }

    // ------------------------------------------------------------ platform

    private static function get(string $key, $default = null)
    {
        return BusinessSetting::where('key', $key)->value('value') ?? $default;
    }

    private static function put(string $key, $value): void
    {
        Helpers::businessUpdateOrInsert(['key' => $key], ['value' => $value]);
    }

    public static function values(): array
    {
        $modules = DB::table('modules')->get(['id', 'module_type', 'variant', 'status']);
        $on = fn ($code) => $modules->contains(fn ($m) => Scope::classify($m->module_type, $m->variant) === $code && $m->status);
        $freeByAmount = self::get('admin_free_delivery_status') && self::get('admin_free_delivery_option') === 'free_delivery_by_order_amount';

        return [
            'appName' => (string) self::get('business_name', ''),
            'supportPhone' => (string) self::get('phone', ''),
            'supportEmail' => (string) self::get('email_address', ''),
            'minVersion' => (string) self::get('app_minimum_version_android', ''),
            'minVersionIos' => (string) self::get('app_minimum_version_ios', ''),
            'foodOn' => $on('food'),
            'groceryOn' => $on('grocery'),
            'petsOn' => $on('pets'),
            'spotsOn' => $on('spots'),
            'freeOver' => $freeByAmount ? (string) self::get('free_delivery_over', '') : '',
            'serviceFee' => self::get('additional_charge_status') ? (string) self::get('additional_charge', '') : '',
            'commission' => (string) self::get('admin_commission', ''),
            'dmCommission' => (string) self::get('delivery_charge_comission', ''),
            'cod' => (bool) (json_decode((string) self::get('cash_on_delivery', ''), true)['status'] ?? false),
            'scheduled' => (bool) self::get('schedule_order', 0),
            'tipping' => (bool) self::get('dm_tips_status', 0),
            'refundsOn' => (bool) self::get('refund_active_status', 0),
            'refundWindow' => (string) \App\Services\OrderComplaintService::windowMinutes(),
            'maintenance' => (bool) self::get('maintenance_mode', 0),
        ];
    }

    public static function save(array $data): array
    {
        $v = Validator::make($data, [
            'appName' => 'required|string|max:191',
            'supportEmail' => 'nullable|email',
            'freeOver' => 'nullable|numeric|min:0',
            'serviceFee' => 'nullable|numeric|min:0',
            'commission' => 'nullable|numeric|between:0,100',
            'dmCommission' => 'nullable|numeric|between:0,100',
            'refundWindow' => 'nullable|integer|min:1|max:10080',
        ]);
        if ($v->fails()) {
            return ['ok' => false, 'message' => $v->errors()->first()];
        }

        self::put('business_name', $data['appName']);
        self::put('phone', $data['supportPhone'] ?? '');
        self::put('email_address', $data['supportEmail'] ?? '');
        self::put('app_minimum_version_android', $data['minVersion'] ?? null);
        self::put('app_minimum_version_ios', $data['minVersionIos'] ?? null);

        $free = $data['freeOver'] ?? '';
        if ($free !== '' && $free !== null) {
            self::put('admin_free_delivery_status', 1);
            self::put('admin_free_delivery_option', 'free_delivery_by_order_amount');
            self::put('free_delivery_over', $free);
        } elseif (self::get('admin_free_delivery_option') === 'free_delivery_by_order_amount') {
            self::put('admin_free_delivery_status', null);
            self::put('admin_free_delivery_option', null);
            self::put('free_delivery_over', null);
        }

        $fee = $data['serviceFee'] ?? '';
        self::put('additional_charge_status', $fee !== '' && $fee !== null ? 1 : null);
        if ($fee !== '' && $fee !== null) {
            self::put('additional_charge', $fee);
        }
        self::put('admin_commission', $data['commission'] ?? 0);
        self::put('delivery_charge_comission', $data['dmCommission'] ?? 0);

        self::put('cash_on_delivery', json_encode(['status' => !empty($data['cod']) ? '1' : '0']));
        self::put('schedule_order', !empty($data['scheduled']) ? 1 : 0);
        self::put('dm_tips_status', !empty($data['tipping']) ? 1 : 0);
        self::put('refund_active_status', !empty($data['refundsOn']) ? 1 : 0);
        if (!empty($data['refundWindow'])) {
            self::put(\App\Services\OrderComplaintService::WINDOW_KEY, (int) $data['refundWindow']);
        }
        self::put('maintenance_mode', !empty($data['maintenance']) ? 1 : 0);

        foreach (self::MODULE_TOGGLES as $key => $code) {
            if (array_key_exists($key, $data) && ($ids = Scope::ids($code))) {
                DB::table('modules')->whereIn('id', $ids)->update(['status' => !empty($data[$key]) ? 1 : 0, 'updated_at' => now()]);
            }
        }

        Cache::forget('business_settings_config_keys');
        return ['ok' => true, 'message' => translate('messages.successfully_updated_to_changes_restart_user_app')];
    }

    // ---------------------------------------------------------------- race

    public static function raceValues(): array
    {
        $values = [];
        foreach (self::RACE as $key => $path) {
            $value = config('placestovisit.' . $path);
            $values[$key] = $key === 'prizeEnabled' ? (bool) $value : $value;
        }
        return $values;
    }

    public static function saveRace(array $data): array
    {
        $rules = [];
        foreach (array_keys(self::RACE) as $key) {
            $rules[$key] = $key === 'prizeEnabled' ? 'boolean' : 'required|numeric|min:0';
        }
        $v = Validator::make($data, $rules);
        if ($v->fails()) {
            return ['ok' => false, 'message' => $v->errors()->first()];
        }
        $stored = [];
        foreach (array_keys(self::RACE) as $key) {
            $stored[$key] = $key === 'prizeEnabled' ? (bool) $data[$key] : $data[$key] + 0;
        }
        self::put(self::RACE_KEY, json_encode($stored));
        Cache::forget(self::RACE_KEY);
        app(LeaderboardService::class)->clearCache();
        return ['ok' => true, 'message' => 'Race settings saved'];
    }

    /** Stored race overrides as config paths, cached for the provider's boot. */
    public static function raceOverrides(): array
    {
        $stored = Cache::remember(self::RACE_KEY, 3600, fn () => json_decode((string) self::get(self::RACE_KEY, ''), true) ?: []);
        $out = [];
        foreach ($stored as $key => $value) {
            if (isset(self::RACE[$key])) {
                $out['placestovisit.' . self::RACE[$key]] = $value;
            }
        }
        return $out;
    }
}
