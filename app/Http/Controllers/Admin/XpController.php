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
        $level = Level::findOrFail($id);
        return view('admin-views.xp.levels.edit', compact('level'));
    }

    public function levelUpdate(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'xp_required' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'badge_image' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:2048',
        ]);

        $level = Level::findOrFail($id);
        
        $data = [
            'name' => $request->name,
            'xp_required' => $request->xp_required,
            'description' => $request->description,
            'status' => $request->status ? 1 : 0,
        ];

        if ($request->hasFile('badge_image')) {
            // Delete old image if exists
            if ($level->badge_image) {
                $oldPath = storage_path('app/public/level/' . $level->badge_image);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            // Store new image
            $imageName = 'level_' . $id . '_' . time() . '.' . $request->badge_image->extension();
            $request->badge_image->storeAs('public/level', $imageName);
            $data['badge_image'] = $imageName;
        }

        $level->update($data);

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
