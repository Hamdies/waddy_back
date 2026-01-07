<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\LevelPrize;
use App\Models\XpChallenge;
use App\Models\XpTransaction;
use App\Models\XpSetting;
use App\Models\User;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;

class XpController extends Controller
{
    // ==================== LEVELS ====================

    public function levels()
    {
        $levels = Level::orderBy('level_number')->get();
        return view('admin-views.xp.levels.index', compact('levels'));
    }

    public function levelEdit($id)
    {
        $level = Level::withoutGlobalScope('translations')->with(['translations', 'prizes'])->findOrFail($id);
        $language = \App\Models\BusinessSetting::where('key', 'language')->first();
        $language = $language->value ?? null;
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $prizeTypes = ['free_delivery', 'wallet_credit'];
        return view('admin-views.xp.levels.edit', compact('level', 'language', 'defaultLang', 'prizeTypes'));
    }

    public function levelUpdate(Request $request, $id)
    {
        $request->validate([
            'name.0' => 'required|string|max:255',
            'xp_required' => 'required|integer|min:0',
            'description.*' => 'nullable|string',
            'badge_image' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:2048',
            'prizes.*.title' => 'nullable|array',
            'prizes.*.prize_type' => 'nullable|in:badge,free_item,free_delivery,discount,wallet_credit,custom',
        ]);

        $level = Level::findOrFail($id);
        
        // Get default description from first element
        $defaultDescription = $request->description[0] ?? null;
        
        $data = [
            'name' => $request->name[0],
            'xp_required' => $request->xp_required,
            'description' => $defaultDescription,
            'status' => $request->status ? 1 : 0,
        ];

        if ($request->hasFile('badge_image')) {
            $data['badge_image'] = Helpers::upload('level/', 'png', $request->file('badge_image'), $level->badge_image);
        }

        $level->update($data);

        // Handle translations for name and description
        $defaultLang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $lang) {
            if ($lang == $defaultLang || $lang == 'default') {
                continue;
            }
            
            // Name translation
            if (isset($request->name[$index]) && $request->name[$index]) {
                \App\Models\Translation::updateOrCreate(
                    [
                        'translationable_type' => 'App\Models\Level',
                        'translationable_id' => $level->id,
                        'locale' => $lang,
                        'key' => 'name'
                    ],
                    ['value' => $request->name[$index]]
                );
            }
            
            // Description translation
            if (isset($request->description[$index]) && $request->description[$index]) {
                \App\Models\Translation::updateOrCreate(
                    [
                        'translationable_type' => 'App\Models\Level',
                        'translationable_id' => $level->id,
                        'locale' => $lang,
                        'key' => 'description'
                    ],
                    ['value' => $request->description[$index]]
                );
            }
        }

        // Get languages for prize title translations
        $languageSetting = \App\Models\BusinessSetting::where('key', 'language')->first();
        $languages = json_decode($languageSetting->value ?? '[]') ?: [];

        // Handle prizes
        $existingPrizeIds = [];
        if ($request->has('prizes')) {
            foreach ($request->prizes as $prizeData) {
                // Get title array - first element is default, rest are language translations
                $titleArray = $prizeData['title'] ?? [];
                $defaultTitle = is_array($titleArray) ? ($titleArray[0] ?? null) : $titleArray;
                
                // Skip empty rows
                if (empty($defaultTitle)) {
                    continue;
                }

                $prizeInfo = [
                    'level_id' => $level->id,
                    'title' => $defaultTitle,
                    'description' => $prizeData['description'] ?? null,
                    'prize_type' => $prizeData['prize_type'] ?? 'custom',
                    'value' => $prizeData['value'] ?? null,
                    'min_order_amount' => $prizeData['min_order_amount'] ?? null,
                    'usage_limit' => $prizeData['usage_limit'] ?? 1,
                    'validity_days' => $prizeData['validity_days'] ?? 30,
                    'status' => isset($prizeData['status']) ? 1 : 0,
                ];

                if (!empty($prizeData['id'])) {
                    // Update existing prize
                    $prize = LevelPrize::find($prizeData['id']);
                    if ($prize && $prize->level_id == $level->id) {
                        $prize->update($prizeInfo);
                        $existingPrizeIds[] = $prize->id;
                        
                        // Handle prize title translations
                        if (is_array($titleArray) && count($languages) > 0) {
                            foreach ($languages as $langIndex => $lang) {
                                $translatedTitle = $titleArray[$langIndex + 1] ?? null;
                                if ($translatedTitle) {
                                    \App\Models\Translation::updateOrCreate(
                                        [
                                            'translationable_type' => 'App\Models\LevelPrize',
                                            'translationable_id' => $prize->id,
                                            'locale' => $lang,
                                            'key' => 'title'
                                        ],
                                        ['value' => $translatedTitle]
                                    );
                                }
                            }
                        }
                    }
                } else {
                    // Create new prize
                    $newPrize = LevelPrize::create($prizeInfo);
                    $existingPrizeIds[] = $newPrize->id;
                    
                    // Handle prize title translations for new prizes
                    if (is_array($titleArray) && count($languages) > 0) {
                        foreach ($languages as $langIndex => $lang) {
                            $translatedTitle = $titleArray[$langIndex + 1] ?? null;
                            if ($translatedTitle) {
                                \App\Models\Translation::updateOrCreate(
                                    [
                                        'translationable_type' => 'App\Models\LevelPrize',
                                        'translationable_id' => $newPrize->id,
                                        'locale' => $lang,
                                        'key' => 'title'
                                    ],
                                    ['value' => $translatedTitle]
                                );
                            }
                        }
                    }
                }
            }
        }

        // Delete prizes that were removed (also delete their translations)
        $deletedPrizes = LevelPrize::where('level_id', $level->id)
            ->whereNotIn('id', $existingPrizeIds)
            ->get();
        
        foreach ($deletedPrizes as $deletedPrize) {
            \App\Models\Translation::where('translationable_type', 'App\Models\LevelPrize')
                ->where('translationable_id', $deletedPrize->id)
                ->delete();
            $deletedPrize->delete();
        }

        Toastr::success(translate('messages.level_updated_successfully'));
        return redirect()->route('admin.users.customer.xp.levels');
    }

    // ==================== PRIZES ====================

    public function prizes()
    {
        $prizes = LevelPrize::with('level')->orderBy('level_id')->paginate(config('default_pagination'));
        $levels = Level::orderBy('level_number')->get();
        return view('admin-views.xp.prizes.index', compact('prizes', 'levels'));
    }

    public function prizeStore(Request $request)
    {
        $request->validate([
            'level_id' => 'required|exists:levels,id',
            'title' => 'required|string|max:255',
            'prize_type' => 'required|in:badge,free_item,free_delivery,discount,wallet_credit,custom',
            'value' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'required|integer|min:1',
            'validity_days' => 'required|integer|min:1',
        ]);

        LevelPrize::create($request->all());

        Toastr::success(translate('messages.prize_created_successfully'));
        return back();
    }

    public function prizeEdit($id)
    {
        $prize = LevelPrize::findOrFail($id);
        $levels = Level::orderBy('level_number')->get();
        return view('admin-views.xp.prizes.edit', compact('prize', 'levels'));
    }

    public function prizeUpdate(Request $request, $id)
    {
        $request->validate([
            'level_id' => 'required|exists:levels,id',
            'title' => 'required|string|max:255',
            'prize_type' => 'required|in:badge,free_item,free_delivery,discount,wallet_credit,custom',
        ]);

        $prize = LevelPrize::findOrFail($id);
        $prize->update($request->all());

        Toastr::success(translate('messages.prize_updated_successfully'));
        return redirect()->route('admin.xp.prizes');
    }

    public function prizeDelete($id)
    {
        LevelPrize::findOrFail($id)->delete();
        Toastr::success(translate('messages.prize_deleted_successfully'));
        return back();
    }

    // ==================== CHALLENGES ====================

    public function challenges()
    {
        $challenges = XpChallenge::orderBy('frequency')->paginate(config('default_pagination'));
        return view('admin-views.xp.challenges.index', compact('challenges'));
    }

    public function challengeStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'challenge_type' => 'required|in:complete_order,min_order_amount,new_store,multiple_orders,specific_category',
            'frequency' => 'required|in:daily,weekly',
            'xp_reward' => 'required|integer|min:1',
            'time_limit_hours' => 'required|integer|min:1',
        ]);

        $conditions = [];
        if ($request->challenge_type === 'min_order_amount' && $request->min_amount) {
            $conditions['min_amount'] = (int) $request->min_amount;
        }
        if ($request->challenge_type === 'multiple_orders' && $request->order_count) {
            $conditions['order_count'] = (int) $request->order_count;
        }

        XpChallenge::create([
            'title' => $request->title,
            'description' => $request->description,
            'challenge_type' => $request->challenge_type,
            'frequency' => $request->frequency,
            'conditions' => $conditions,
            'xp_reward' => $request->xp_reward,
            'time_limit_hours' => $request->time_limit_hours,
            'status' => $request->status ? 1 : 0,
        ]);

        Toastr::success(translate('messages.challenge_created_successfully'));
        return back();
    }

    public function challengeEdit($id)
    {
        $challenge = XpChallenge::findOrFail($id);
        return view('admin-views.xp.challenges.edit', compact('challenge'));
    }

    public function challengeUpdate(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'challenge_type' => 'required',
            'frequency' => 'required|in:daily,weekly',
            'xp_reward' => 'required|integer|min:1',
        ]);

        $conditions = [];
        if ($request->challenge_type === 'min_order_amount' && $request->min_amount) {
            $conditions['min_amount'] = (int) $request->min_amount;
        }
        if ($request->challenge_type === 'multiple_orders' && $request->order_count) {
            $conditions['order_count'] = (int) $request->order_count;
        }

        $challenge = XpChallenge::findOrFail($id);
        $challenge->update([
            'title' => $request->title,
            'description' => $request->description,
            'challenge_type' => $request->challenge_type,
            'frequency' => $request->frequency,
            'conditions' => $conditions,
            'xp_reward' => $request->xp_reward,
            'time_limit_hours' => $request->time_limit_hours ?? 24,
            'status' => $request->status ? 1 : 0,
        ]);

        Toastr::success(translate('messages.challenge_updated_successfully'));
        return redirect()->route('admin.xp.challenges');
    }

    public function challengeDelete($id)
    {
        XpChallenge::findOrFail($id)->delete();
        Toastr::success(translate('messages.challenge_deleted_successfully'));
        return back();
    }

    public function challengeStatus(Request $request)
    {
        $challenge = XpChallenge::findOrFail($request->id);
        $challenge->status = !$challenge->status;
        $challenge->save();

        Toastr::success(translate('messages.status_updated'));
        return back();
    }

    // ==================== SETTINGS ====================

    public function settings()
    {
        $settings = XpSetting::all()->pluck('value', 'key');
        return view('admin-views.xp.settings', compact('settings'));
    }

    public function settingsUpdate(Request $request)
    {
        $keys = [
            'xp_per_order', 'xp_per_review', 'xp_daily_challenge', 'xp_weekly_challenge',
            'multiplier_food', 'multiplier_pharmacy', 'multiplier_grocery', 'multiplier_parcel',
            'prize_validity_days', 'leveling_status'
        ];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                XpSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $request->$key]
                );
            }
        }

        Toastr::success(translate('messages.settings_updated_successfully'));
        return back();
    }

    // ==================== USER XP REPORT ====================

    public function users(Request $request)
    {
        $search = $request->search;
        
        $users = User::where('total_xp', '>', 0)
            ->when($search, function($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('f_name', 'like', "%{$search}%")
                      ->orWhere('l_name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('total_xp')
            ->paginate(config('default_pagination'));

        return view('admin-views.xp.users.index', compact('users', 'search'));
    }

    public function userDetail($id)
    {
        $user = User::with(['xpTransactions' => function($q) {
            $q->latest()->take(50);
        }, 'levelPrizes.prize.level', 'userChallenges.challenge'])->findOrFail($id);
        
        $currentLevel = Level::where('level_number', $user->level)->first();
        $nextLevel = Level::where('level_number', $user->level + 1)->first();

        return view('admin-views.xp.users.detail', compact('user', 'currentLevel', 'nextLevel'));
    }

    public function addUserXp(Request $request, $id)
    {
        $request->validate([
            'xp_amount' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $user = User::findOrFail($id);
        
        // Use XpService to add XP (handles level up automatically)
        $transaction = \App\Services\XpService::addXp(
            $user,
            'admin_manual',
            $request->xp_amount,
            'admin',
            auth('admin')->id(),
            $request->description ?? 'Manually added by admin'
        );

        if ($transaction) {
            Toastr::success(translate('messages.xp_added_successfully'));
        } else {
            Toastr::warning(translate('messages.xp_system_disabled'));
        }

        return back();
    }

    public function transactions(Request $request)
    {
        $filter = $request->query('filter', 'all');
        
        $transactions = XpTransaction::with('user')
            ->when($filter === 'order', fn($q) => $q->where('reference_type', 'order'))
            ->when($filter === 'review', fn($q) => $q->where('reference_type', 'review'))
            ->when($filter === 'challenge', fn($q) => $q->where('reference_type', 'challenge'))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->latest()
            ->paginate(config('default_pagination'));

        return view('admin-views.xp.transactions.index', compact('transactions', 'filter'));
    }
}
