<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Category;
use App\Models\Module;
use App\Models\PetReminder;
use App\Models\UserPet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pet lifecycle pushes (PET-13, PET-16, PET-10): food running low, birthday,
 * life stage, and the reminders customers set themselves.
 *
 * Guardrails, all here so no job can skip them:
 * - the pet's own `notify` switch;
 * - one push per (pet, kind, ref), ever: `pet_push_log`;
 * - automatic pushes (food, life stage) at most once per pet per 7 days.
 *   A birthday and a reminder the customer asked for are exempt: one comes
 *   once a year, the other was requested;
 * - daytime only: the daily jobs run at 10:00 / 18:00 Cairo, reminders hold
 *   anything due at night until 09:00.
 *
 * Copy is in `resources/lang/{en,ar}/pets.php`, in the customer's app
 * language, `_m`/`_f` by the pet's sex (PET-07).
 *
 * Every push is `type: pets` with the pet id in `data_id`. The app opens the
 * Pets module on it.
 */
class PetPushService
{
    private const WEEKLY_CAP_DAYS = 7;

    /** A typical bag lasts about this long when the order history can't say. */
    private const DEFAULT_FOOD_DAYS = [
        'cat' => 30, 'dog' => 21, 'bird' => 30, 'fish' => 45, 'small' => 30,
    ];

    /** Nudge this many days before the estimated run-out. */
    private const REPLENISH_LEAD_DAYS = 3;

    /** After run-out + this, the estimate is stale and nothing is sent. */
    private const REPLENISH_GRACE_DAYS = 7;

    private ?int $moduleId = null;

    private function petsModuleId(): ?int
    {
        return $this->moduleId ??= Module::where('variant', 'pets')->value('id');
    }

    private function today(): Carbon
    {
        return now(config('placestovisit.timezone', 'Africa/Cairo'))->startOfDay();
    }

    // ==================== Jobs ====================

    /** Pets whose real (not estimated) birthday is today. */
    public function birthdays(): int
    {
        $today = $this->today();
        $sent = 0;
        $pets = UserPet::with('user')
            ->where('notify', true)
            ->where('birth_date_is_estimate', false)
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $today->month)
            ->when(
                // A Feb 29 pet celebrates on Feb 28 in a non-leap year.
                $today->month === 2 && $today->day === 28 && !$today->isLeapYear(),
                fn ($q) => $q->whereIn(DB::raw('DAY(birth_date)'), [28, 29]),
                fn ($q) => $q->whereDay('birth_date', $today->day)
            )
            ->get();

        foreach ($pets as $pet) {
            $age = (int) $pet->birth_date->diffInYears($today);
            if ($age < 1) {
                continue;
            }
            $code = config('pets.birthday_coupon_code');
            $sent += (int) $this->send($pet, 'birthday', (string) $today->year, capped: false, copy: function ($locale, $sx) use ($pet, $age, $code) {
                $body = trans("pets.birthday_body{$sx}", ['name' => $pet->name, 'age' => $this->years($age, $locale)], $locale);
                if ($code) {
                    $body .= trans('pets.birthday_code', ['code' => $code], $locale);
                }
                return [trans("pets.birthday_title{$sx}", ['name' => $pet->name], $locale), $body];
            });
        }
        return $sent;
    }

    /**
     * Cats and dogs turning 1 (baby → adult food) or 7 (senior food).
     *
     * A three-day window rather than "exactly today", so a missed scheduler
     * run still lands; the log keeps it to one push per milestone.
     */
    public function lifeStages(): int
    {
        $today = $this->today();
        $sent = 0;
        $pets = UserPet::with('user')
            ->where('notify', true)
            ->whereIn('species', ['cat', 'dog'])
            ->whereNotNull('birth_date')
            ->where('birth_date', '<=', $today->copy()->subYear())
            ->get();

        foreach ($pets as $pet) {
            foreach ([1 => 'adult', 7 => 'senior'] as $years => $stage) {
                $milestone = $pet->birth_date->copy()->addYears($years);
                if ($milestone->gt($today) || $milestone->lt($today->copy()->subDays(2))) {
                    continue;
                }
                $sent += (int) $this->send($pet, 'life_stage', $stage, capped: true, copy: fn ($locale, $sx) => [
                    trans("pets.{$stage}_title{$sx}", ['name' => $pet->name], $locale),
                    trans("pets.{$stage}_body{$sx}", [
                        'name' => $pet->name,
                        'baby' => trans("pets.baby_{$pet->species}", [], $locale),
                    ], $locale),
                ]);
            }
        }
        return $sent;
    }

    /**
     * "Luna's food might be running low": a few days before the last bag of
     * food is estimated to run out.
     *
     * The estimate is the customer's own rhythm when the history has it (the
     * gap between their last two orders of that product), else a typical bag
     * for the species. Skipped when they set their own reminder for it.
     */
    public function replenish(): int
    {
        $moduleId = $this->petsModuleId();
        if (!$moduleId) {
            return 0;
        }
        // Food shelves of the pets tree: `cat.food` → species `cat`.
        $foodCategories = Category::where('module_id', $moduleId)
            ->where('code', 'like', '%.food')
            ->pluck('code', 'id')
            ->map(fn ($code) => explode('.', $code)[0]);
        if ($foodCategories->isEmpty()) {
            return 0;
        }

        $today = $this->today();
        $sent = 0;
        $households = UserPet::with('user')->where('notify', true)->get()->groupBy('user_id');

        foreach ($households as $userId => $pets) {
            $lines = DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->join('items', 'items.id', '=', 'order_details.item_id')
                ->where('orders.user_id', $userId)
                ->where('orders.is_guest', 0)
                ->where('orders.module_id', $moduleId)
                ->where('orders.order_status', 'delivered')
                ->where('orders.created_at', '>=', $today->copy()->subDays(180))
                ->whereIn('items.category_id', $foodCategories->keys())
                ->orderByDesc('orders.created_at')
                ->get(['orders.id as order_id', 'orders.created_at', 'order_details.item_id', 'items.category_id']);
            if ($lines->isEmpty()) {
                continue;
            }

            $last = $lines->first();
            $hasReminder = PetReminder::where('user_id', $userId)
                ->where('item_id', $last->item_id)
                ->where('active', true)
                ->exists();
            if ($hasReminder) {
                continue;
            }

            $species = $foodCategories[(int) $last->category_id];
            $lastAt = Carbon::parse($last->created_at)->startOfDay();
            $previous = $lines->first(fn ($l) => $l->item_id == $last->item_id && $l->order_id != $last->order_id);
            $cycle = $previous
                ? max(7, min(90, $lastAt->diffInDays(Carbon::parse($previous->created_at)->startOfDay())))
                : self::DEFAULT_FOOD_DAYS[$species];

            $runOut = $lastAt->copy()->addDays((int) $cycle);
            if ($today->lt($runOut->copy()->subDays(self::REPLENISH_LEAD_DAYS))
                || $today->gt($runOut->copy()->addDays(self::REPLENISH_GRACE_DAYS))) {
                continue;
            }

            $pet = $this->petFor($pets, $species);
            $days = (int) $lastAt->diffInDays($today);
            $sent += (int) $this->send($pet, 'replenish', "order:{$last->order_id}", capped: true, copy: fn ($locale, $sx) => [
                trans("pets.replenish_title{$sx}", ['name' => $pet->name], $locale),
                trans("pets.replenish_body{$sx}", ['days' => $days], $locale),
            ]);
        }
        return $sent;
    }

    /**
     * Reminders the customer set ("every 4 weeks"), due now. Runs hourly so
     * a reminder keeps the time of day it was set at, but holds anything
     * that falls due at night until 09:00 Cairo.
     */
    public function reminders(): int
    {
        $hour = now(config('placestovisit.timezone', 'Africa/Cairo'))->hour;
        if ($hour < 9 || $hour >= 21) {
            return 0;
        }
        $sent = 0;
        $due = PetReminder::with(['user', 'pet', 'item'])
            ->where('active', true)
            ->where('next_at', '<=', now())
            ->get();

        foreach ($due as $reminder) {
            $pet = $reminder->pet
                ?? UserPet::where('user_id', $reminder->user_id)->orderByDesc('is_primary')->orderBy('id')->first();
            // Rescheduled whatever happens, so one failure doesn't retry hourly.
            $reminder->next_at = now()->addDays($reminder->interval_days);
            $reminder->last_sent_at = now();
            $reminder->save();

            if (!$pet || !$reminder->item) {
                continue;
            }
            $weeks = intdiv($reminder->interval_days, 7);
            $sent += (int) $this->send($pet, 'reminder', "rem:{$reminder->id}:" . now()->toDateString(), capped: false, copy: fn ($locale, $sx) => [
                trans("pets.reminder_title{$sx}", ['name' => $pet->name, 'item' => $reminder->item->name], $locale),
                trans("pets.reminder_body{$sx}", ['weeks' => $weeks], $locale),
            ]);
        }
        return $sent;
    }

    // ==================== Sending ====================

    /**
     * Sends one push about [pet], unless a guardrail says no. Returns
     * whether it went out.
     *
     * @param callable(string $locale, string $sexSuffix): array{0: string, 1: string} $copy
     */
    private function send(UserPet $pet, string $kind, string $ref, bool $capped, callable $copy): bool
    {
        $user = $pet->user;
        if (!$pet->notify || !$user || !$user->cm_firebase_token) {
            return false;
        }
        if ($capped && $pet->last_pushed_at && $pet->last_pushed_at->gt(now()->subDays(self::WEEKLY_CAP_DAYS))) {
            return false;
        }
        $logged = DB::table('pet_push_log')->insertOrIgnore([
            'user_pet_id' => $pet->id,
            'kind' => $kind,
            'ref' => $ref,
            'created_at' => now(),
        ]);
        if ($logged === 0) {
            return false; // already sent
        }

        $locale = $user->current_language_key === 'ar' ? 'ar' : 'en';
        $sx = $pet->sex === 'female' ? '_f' : '_m';
        [$title, $body] = $copy($locale, $sx);

        $data = [
            'title' => $title,
            'description' => $body,
            'image' => $pet->photo_full_url ?? '',
            'type' => 'pets',
            'data_id' => (string) $pet->id,
            'module_id' => (string) ($this->petsModuleId() ?? ''),
            'order_id' => '',
            'order_type' => '',
        ];

        try {
            Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);
            DB::table('user_notifications')->insert([
                'data' => json_encode($data),
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $pet->forceFill(['last_pushed_at' => now()])->save();
            return true;
        } catch (\Throwable $e) {
            Log::error("Pet push {$kind} failed for pet {$pet->id}: " . $e->getMessage());
            return false;
        }
    }

    /** The pet a food line is about: one of that species (primary first), else the primary. */
    private function petFor(Collection $pets, string $species): UserPet
    {
        $sorted = $pets->sortByDesc('is_primary')->values();
        return $sorted->firstWhere('species', $species) ?? $sorted->first();
    }

    /** "3 years" / "3 سنين": Arabic counts 1, 2, 3–10 and 11+ differently. */
    private function years(int $n, string $locale): string
    {
        if ($n === 1) {
            return trans('pets.years_1', [], $locale);
        }
        if ($locale === 'ar') {
            if ($n === 2) {
                return trans('pets.years_2', [], $locale);
            }
            if ($n <= 10) {
                return trans('pets.years_few', ['n' => $n], $locale);
            }
        }
        return trans('pets.years_n', ['n' => $n], $locale);
    }
}
