<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Cuisine;
use App\Models\Translation;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cuisines are module-scoped store types: "Pizza" for the food home,
 * "Supermarkets" / "Dairy" for the grocery home. A NULL module_id shows in
 * every module. Names are unique per module, not globally.
 */
class CuisineController extends Controller
{
    public function index(Request $request)
    {
        $search = $request['search'];

        $cuisines = Cuisine::withoutGlobalScope('translate')
            ->when($search, function ($query) use ($search) {
                $keys = explode(' ', $search);
                foreach ($keys as $key) {
                    $query->where('name', 'like', "%{$key}%");
                }
            })
            ->with('module')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate(config('default_pagination'))
            ->appends(['search' => $search]);

        return view('admin-views.cuisine.index', compact('cuisines', 'search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|array',
            'name.0' => ['required', Rule::unique('cuisines', 'name')->where(fn ($q) => $request->module_id ? $q->where('module_id', $request->module_id) : $q->whereNull('module_id'))],
            'module_id' => 'nullable|exists:modules,id',
            'name.*' => 'max:191',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'priority' => 'nullable|integer|min:0',
        ], [
            'name.0.required' => translate('default_name_is_required'),
        ]);

        $cuisine = new Cuisine();
        $cuisine->name = $request->name[array_search('default', $request->lang)];
        $cuisine->image = $request->file('image')
            ? Helpers::upload('cuisine/', 'png', $request->file('image'))
            : null;
        $cuisine->priority = $request->priority ?? 0;
        $cuisine->module_id = $request->module_id ?: null;
        $cuisine->status = 1;
        $cuisine->save();

        $this->saveTranslations($request, $cuisine, insert: true);

        Toastr::success(translate('messages.store_type_added_successfully'));

        return back();
    }

    public function edit($id)
    {
        $cuisine = Cuisine::withoutGlobalScope('translate')->findOrFail($id);

        return view('admin-views.cuisine.edit', compact('cuisine'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|array',
            'name.0' => ['required', Rule::unique('cuisines', 'name')->ignore($id)->where(fn ($q) => $request->module_id ? $q->where('module_id', $request->module_id) : $q->whereNull('module_id'))],
            'module_id' => 'nullable|exists:modules,id',
            'name.*' => 'max:191',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'priority' => 'nullable|integer|min:0',
        ], [
            'name.0.required' => translate('default_name_is_required'),
        ]);

        $cuisine = Cuisine::withoutGlobalScope('translate')->findOrFail($id);
        $cuisine->name = $request->name[array_search('default', $request->lang)];
        if ($request->has('image')) {
            $cuisine->image = Helpers::update('cuisine/', $cuisine->image, 'png', $request->file('image'));
        }
        $cuisine->priority = $request->priority ?? 0;
        $cuisine->module_id = $request->module_id ?: null;
        $cuisine->save();

        $this->saveTranslations($request, $cuisine, insert: false);

        Toastr::success(translate('messages.store_type_updated_successfully'));

        return redirect()->route('admin.cuisine.index');
    }

    public function status(Request $request, $id, $status)
    {
        $cuisine = Cuisine::findOrFail($id);
        $cuisine->status = $status;
        $cuisine->save();

        Toastr::success(translate('messages.store_type_status_updated'));

        return back();
    }

    public function destroy($id)
    {
        $cuisine = Cuisine::findOrFail($id);

        if ($cuisine->image) {
            Helpers::check_and_delete('cuisine/', $cuisine->image);
        }

        // The pivot is cascade-deleted by the FK, but the polymorphic
        // translations have no constraint pointing at this row.
        $cuisine->translations()->delete();
        $cuisine->delete();

        Toastr::success(translate('messages.store_type_deleted_successfully'));

        return back();
    }

    /**
     * Mirrors the pattern every other translatable admin resource uses: the
     * default-locale name lives on the row, every other locale in translations.
     */
    private function saveTranslations(Request $request, Cuisine $cuisine, bool $insert): void
    {
        $default_lang = str_replace('_', '-', app()->getLocale());
        $data = [];

        foreach ($request->lang as $index => $key) {
            if ($key === 'default') {
                continue;
            }

            $value = ($default_lang == $key && !($request->name[$index]))
                ? $cuisine->getRawOriginal('name')
                : ($request->name[$index] ?? null);

            if (!$value) {
                continue;
            }

            if ($insert) {
                $data[] = [
                    'translationable_type' => 'App\Models\Cuisine',
                    'translationable_id' => $cuisine->id,
                    'locale' => $key,
                    'key' => 'name',
                    'value' => $value,
                ];
            } else {
                Translation::updateOrInsert(
                    [
                        'translationable_type' => 'App\Models\Cuisine',
                        'translationable_id' => $cuisine->id,
                        'locale' => $key,
                        'key' => 'name',
                    ],
                    ['value' => $value]
                );
            }
        }

        if ($insert && $data) {
            Translation::insert($data);
        }
    }
}
