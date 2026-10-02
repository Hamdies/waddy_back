<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\Module;
use App\Models\PetReminder;
use App\Models\UserPet;
use App\Models\VetClinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The Pets module's own endpoints: the customer's pets and nearby vet clinics.
 *
 * Shops, products, cart and orders are not here. The Pets module is a
 * grocery-typed module (`modules.variant = pets`), so those run through the
 * ordinary store/item/order endpoints (PET-01, docs/pets_module_plan.md).
 */
class PetController extends Controller
{
    private const PHOTO_DIR = 'pet/';

    /** Below this many reviews a clinic shows no star at all (PET-04). */
    private const MIN_REVIEWS_FOR_RATING = 5;

    // ==================== Pets ====================

    /** GET /api/v1/customer/pets/list */
    public function index(Request $request): JsonResponse
    {
        $pets = $request->user()->pets()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return response()->json($pets);
    }

    /** POST /api/v1/customer/pets/add (multipart when a photo is sent) */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate($this->rules(creating: true));

        if ($user->pets()->count() >= UserPet::MAX_PER_USER) {
            return response()->json([
                'errors' => [['code' => 'pets', 'message' => translate('messages.pet_limit_reached')]],
            ], 403);
        }

        $pet = DB::transaction(function () use ($user, $data, $request) {
            $pet = new UserPet($data);
            $pet->user_id = $user->id;
            // The first pet is the one the shop opens on.
            $pet->is_primary = !$user->pets()->exists() || ($data['is_primary'] ?? false);
            if ($request->hasFile('photo')) {
                $pet->photo = $this->savePhoto($request->file('photo'));
            }
            $pet->save();

            if ($pet->is_primary) {
                $this->demoteOthers($pet);
            }

            return $pet;
        });

        return response()->json($pet->fresh(), 201);
    }

    /**
     * POST /api/v1/customer/pets/update/{id}
     *
     * POST, not PUT: PHP does not parse multipart bodies on PUT, and the photo
     * is sent multipart. Only the fields sent are changed.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pet = $request->user()->pets()->findOrFail($id);
        $data = $request->validate($this->rules(creating: false));

        DB::transaction(function () use ($pet, $data, $request) {
            $pet->fill($data);

            if ($request->hasFile('photo')) {
                $old = $pet->photo;
                $pet->photo = $this->savePhoto($request->file('photo'));
                if ($old) {
                    Helpers::check_and_delete(self::PHOTO_DIR, $old);
                }
            } elseif ($request->boolean('remove_photo') && $pet->photo) {
                Helpers::check_and_delete(self::PHOTO_DIR, $pet->photo);
                $pet->photo = null;
            }

            $pet->save();

            if ($pet->is_primary) {
                $this->demoteOthers($pet);
            }
        });

        return response()->json($pet->fresh());
    }

    /** DELETE /api/v1/customer/pets/delete/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $pet = $request->user()->pets()->findOrFail($id);

        DB::transaction(function () use ($pet, $request) {
            $wasPrimary = $pet->is_primary;
            $pet->is_primary = false;
            $pet->save();
            // Soft delete keeps the photo: order history and pushes already
            // sent may still name this pet.
            $pet->delete();

            if ($wasPrimary) {
                $request->user()->pets()->orderBy('id')->first()?->update(['is_primary' => true]);
            }
        });

        return response()->json(['message' => translate('messages.pet_removed')]);
    }

    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:32'],
            'species' => [$required, Rule::in(UserPet::SPECIES)],
            'sex' => ['sometimes', Rule::in(UserPet::SEXES)],
            'age_band' => ['nullable', Rule::in(UserPet::AGE_BANDS)],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after:-40 years'],
            'birth_date_is_estimate' => ['sometimes', 'boolean'],
            'diet' => ['nullable', Rule::in(UserPet::DIETS)],
            'breed' => ['nullable', 'string', 'max:64'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'is_primary' => ['sometimes', 'boolean'],
            'notify' => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    private function savePhoto($file): ?string
    {
        $name = Helpers::upload(self::PHOTO_DIR, 'png', $file);
        // `upload` swallows storage errors and returns its placeholder name.
        return $name === 'def.png' ? null : $name;
    }

    private function demoteOthers(UserPet $pet): void
    {
        UserPet::where('user_id', $pet->user_id)
            ->where('id', '!=', $pet->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    // ==================== The usual + reminders ====================

    /**
     * GET /api/v1/customer/pets/usual
     *
     * The hub's "Luna's usual" card: the last food the customer got
     * delivered from a pet shop (any pet product if they never bought food),
     * with the reminder they set for it, if any. Null when they have never
     * ordered from a pet shop.
     */
    public function usual(Request $request): JsonResponse
    {
        $moduleId = Module::where('variant', 'pets')->value('id');
        if (!$moduleId) {
            return response()->json(null);
        }
        $foodIds = Category::where('module_id', $moduleId)->where('code', 'like', '%.food')->pluck('id');

        $lines = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('items', 'items.id', '=', 'order_details.item_id')
            ->where('orders.user_id', $request->user()->id)
            ->where('orders.is_guest', 0)
            ->where('orders.module_id', $moduleId)
            ->where('orders.order_status', 'delivered')
            ->orderByDesc('orders.created_at')
            ->limit(50)
            ->get(['order_details.item_id', 'items.category_id', 'orders.created_at', 'orders.store_id']);
        $line = $lines->first(fn ($l) => $foodIds->contains((int) $l->category_id)) ?? $lines->first();
        if (!$line) {
            return response()->json(null);
        }

        $item = Item::active()->find($line->item_id);
        if (!$item) {
            return response()->json(null);
        }
        $reminder = PetReminder::where('user_id', $request->user()->id)
            ->where('item_id', $item->id)
            ->where('active', true)
            ->first();

        return response()->json([
            'item' => Helpers::product_data_formatting($item, false, false, app()->getLocale()),
            'store_id' => (int) $line->store_id,
            'last_ordered_at' => $line->created_at,
            'reminder' => $reminder ? $this->formatReminder($reminder) : null,
        ]);
    }

    /**
     * POST /api/v1/customer/pets/reminders
     *
     * "Remind me every N weeks" for one product. One reminder per product:
     * setting it again changes the interval and restarts the clock.
     */
    public function setReminder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => 'required|integer|exists:items,id',
            'store_id' => 'required|integer',
            'interval_days' => ['required', 'integer', Rule::in(PetReminder::INTERVALS)],
            'user_pet_id' => 'nullable|integer',
        ]);
        $petId = isset($data['user_pet_id'])
            ? $request->user()->pets()->whereKey($data['user_pet_id'])->value('id')
            : null;

        $reminder = PetReminder::where('user_id', $request->user()->id)
            ->where('item_id', $data['item_id'])
            ->first() ?? new PetReminder();
        $reminder->user_id = $request->user()->id;
        $reminder->fill([
            'user_pet_id' => $petId,
            'item_id' => $data['item_id'],
            'store_id' => $data['store_id'],
            'interval_days' => $data['interval_days'],
            'next_at' => now()->addDays($data['interval_days']),
            'active' => true,
        ])->save();

        return response()->json($this->formatReminder($reminder));
    }

    /** DELETE /api/v1/customer/pets/reminders/{id} */
    public function deleteReminder(Request $request, int $id): JsonResponse
    {
        PetReminder::where('user_id', $request->user()->id)->whereKey($id)->delete();
        return response()->json(['message' => translate('messages.pet_reminder_removed')]);
    }

    private function formatReminder(PetReminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'item_id' => $reminder->item_id,
            'interval_days' => $reminder->interval_days,
            'next_at' => $reminder->next_at?->toIso8601String(),
        ];
    }

    // ==================== Categories ====================

    /**
     * GET /api/v1/pets/categories
     *
     * The pets tree in one request: species (main) → needs (subs), each with
     * the stable `code` the app keys on (`cat`, `cat.food`, `all`). The app's
     * generic category list is cached per module and fetched one level at a
     * time; the store page needs the whole tree up front to build its species
     * switcher and rails, and it needs the codes.
     */
    public function categories(): JsonResponse
    {
        $moduleId = Module::where('variant', 'pets')->value('id');
        if (!$moduleId) {
            return response()->json([]);
        }

        $rows = Category::where('module_id', $moduleId)
            ->whereNull('store_id')
            ->whereNotNull('code')
            ->where('status', 1)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $format = fn (Category $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'image_full_url' => $c->image ? $c->image_full_url : null,
        ];

        $tree = $rows->where('parent_id', 0)->values()->map(fn (Category $main) => $format($main) + [
            'children' => $rows->where('parent_id', $main->id)->values()->map($format)->all(),
        ]);

        return response()->json($tree);
    }

    // ==================== Clinics ====================

    /**
     * GET /api/v1/pets/clinics?lat=&lng=&radius=
     *
     * Nearby vet clinics, nearest first, from the Pets module's own
     * `vet_clinics` table (apart from Spots since 10-02).
     */
    public function clinics(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:50',
        ]);
        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $radius = (float) ($request->radius ?? 15);
        // LEAST(1, …) so rounding can't push acos() past its domain and
        // return NULL for a clinic sitting exactly at the customer's point.
        $haversine = '(6371 * acos(LEAST(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))';

        $clinics = VetClinic::active()
            ->select('vet_clinics.*')
            ->selectRaw("{$haversine} AS distance", [$lat, $lng, $lat])
            ->having('distance', '<', $radius)
            ->orderBy('distance')
            ->orderByDesc('priority')
            ->limit(30)
            ->get()
            ->map(fn (VetClinic $clinic) => $this->formatClinic($clinic))
            ->values();

        return response()->json($clinics);
    }

    private function formatClinic(VetClinic $clinic): array
    {
        return [
            'id' => $clinic->id,
            'name' => $clinic->localizedName(),
            'description' => $clinic->localizedDescription(),
            'address' => $clinic->address,
            'latitude' => $clinic->latitude,
            'longitude' => $clinic->longitude,
            'distance_km' => $clinic->distance !== null ? round((float) $clinic->distance, 2) : null,
            'phone' => $clinic->phone,
            'whatsapp' => self::whatsappNumber($clinic->phone),
            'website' => $clinic->website,
            'instagram' => $clinic->instagram,
            'image' => $clinic->logoUrl(),
            'cover_image' => $clinic->coverUrl(),
            'opening_hours' => $clinic->opening_hours,
            'species' => array_values($clinic->species ?? []),
            'services' => array_values($clinic->services ?? []),
            'service_prices' => (object) ($clinic->service_prices ?? []),
            'vets' => array_values($clinic->vets ?? []),
            'today_hours' => $clinic->todayHours(),
            'is_open_now' => $clinic->isOpenNow(),
            // No reviews for clinics yet (PET-18): no number rather than a made-up one.
            'rating' => null,
            'reviews_count' => 0,
        ];
    }

    /**
     * The number in international form for `wa.me/<number>`, or null.
     *
     * Only Egyptian mobiles (01x…) can be on WhatsApp; a landline (02…) gives
     * null so the app hides the button instead of opening a dead chat.
     */
    public static function whatsappNumber(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 2);
        }
        if (preg_match('/^01[0125]\d{8}$/', $digits)) {
            return '2' . $digits;
        }
        if (preg_match('/^201[0125]\d{8}$/', $digits)) {
            return $digits;
        }
        return null;
    }
}
