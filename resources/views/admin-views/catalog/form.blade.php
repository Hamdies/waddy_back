@extends('layouts.admin.app')

@section('title', $product->exists ? $product->name : translate('messages.catalog_new_product'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap __gap-15px justify-content-between align-items-center">
            <h1 class="page-header-title">
                <a href="{{ route('admin.item.catalog.index') }}" class="page-header-icon text-body"><i class="tio-arrow-backward"></i></a>
                <span>{{ $product->exists ? $product->name : translate('messages.catalog_new_product') }}</span>
            </h1>
            @if ($product->exists)
                <a href="{{ route('admin.item.catalog.merge-preview', $product->id) }}" class="btn btn-outline-secondary">
                    <i class="tio-merge"></i> {{ translate('messages.catalog_merge_duplicate') }}
                </a>
            @endif
        </div>

        @if ($product->exists)
            <div class="alert alert-soft-primary mb-3" role="alert">
                {{ translate('messages.catalog_shared_notice', ['count' => $listings->count()]) }}
            </div>
        @endif

        <form method="post" enctype="multipart/form-data"
              action="{{ $product->exists ? route('admin.item.catalog.update', $product->id) : route('admin.item.catalog.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title mb-0">{{ translate('messages.catalog_content') }}</h5></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="input-label">{{ translate('messages.name') }} ({{ translate('messages.default') }}) <span class="text-danger">*</span></label>
                                <input type="text" name="name[default]" maxlength="191" required class="form-control"
                                       value="{{ old('name.default', $product->name) }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label">{{ translate('messages.description') }} ({{ translate('messages.default') }})</label>
                                <textarea name="description[default]" rows="3" maxlength="1000" class="form-control">{{ old('description.default', $product->description) }}</textarea>
                            </div>

                            @foreach ($languages as $lang)
                                <div class="border rounded p-3 mb-3">
                                    <h6 class="mb-2">{{ \App\CentralLogics\Helpers::get_language_name($lang) }} ({{ strtoupper($lang) }})</h6>
                                    <div class="form-group">
                                        <label class="input-label">{{ translate('messages.name') }}</label>
                                        <input type="text" name="name[{{ $lang }}]" maxlength="191" class="form-control"
                                               dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}"
                                               value="{{ old("name.$lang", $translations["name.$lang"] ?? '') }}">
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('messages.description') }}</label>
                                        <textarea name="description[{{ $lang }}]" rows="2" maxlength="1000" class="form-control"
                                                  dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">{{ old("description.$lang", $translations["description.$lang"] ?? '') }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header"><h5 class="card-title mb-0">{{ translate('messages.images') }}</h5></div>
                        <div class="card-body">
                            @if ($product->image)
                                <img src="{{ $product->image_full_url }}" alt="" class="rounded border mb-2 w-100 object-contain" style="max-height: 180px;">
                            @endif
                            <label class="input-label">{{ $product->image ? translate('messages.replace_image') : translate('messages.image') }}</label>
                            <input type="file" name="image" accept="image/*" class="form-control mb-3">

                            @php
                                $gallery = app(\App\Services\CatalogService::class)->decodeImages($product->images);
                            @endphp
                            @if ($gallery)
                                <label class="input-label">{{ translate('messages.catalog_gallery_remove_hint') }}</label>
                                <div class="d-flex flex-wrap __gap-12px mb-3">
                                    @foreach ($gallery as $i => $image)
                                        <label class="text-center mb-0">
                                            <img src="{{ $product->images_full_url[$i] ?? '' }}" alt="" class="rounded border d-block mb-1" style="width: 72px; height: 72px; object-fit: cover;">
                                            <input type="checkbox" name="remove_images[]" value="{{ $image['img'] }}">
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            <label class="input-label">{{ translate('messages.add_images') }}</label>
                            <input type="file" name="images[]" accept="image/*" multiple class="form-control">
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">{{ translate('messages.details') }}</h5></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="input-label">{{ translate('messages.category') }} <span class="text-danger">*</span></label>
                                <select name="category_id" required class="form-control js-select2-custom">
                                    <option value="">{{ translate('messages.select_category') }}</option>
                                    @foreach ($categories as $main)
                                        <optgroup label="{{ $main->name }}">
                                            <option value="{{ $main->id }}" @selected(old('category_id', $product->category_id) == $main->id)>{{ $main->name }}</option>
                                            @foreach ($main->childes as $sub)
                                                <option value="{{ $sub->id }}" @selected(old('category_id', $product->category_id) == $sub->id)>{{ $main->name }} › {{ $sub->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="input-label">{{ translate('messages.unit') }}</label>
                                <select name="unit_id" class="form-control">
                                    <option value="">—</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" @selected(old('unit_id', $product->unit_id) == $unit->id)>{{ $unit->unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="input-label">{{ translate('messages.barcode') }}</label>
                                <input type="text" name="barcode" maxlength="32" inputmode="numeric" class="form-control"
                                       value="{{ old('barcode', $product->barcode) }}" placeholder="EAN-13">
                            </div>
                            <div class="form-group mb-0">
                                <label class="toggle-switch toggle-switch-sm d-flex justify-content-between align-items-center">
                                    <span>{{ translate('messages.status') }}</span>
                                    <input type="hidden" name="status" value="0">
                                    <input type="checkbox" name="status" value="1" class="toggle-switch-input" @checked(old('status', $product->status))>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="btn--container justify-content-end mt-3">
                <button type="submit" class="btn btn--primary">
                    {{ $product->exists ? translate('messages.catalog_save_all_stores') : translate('messages.catalog_create_product') }}
                </button>
            </div>
        </form>

        @if ($product->exists)
            <div class="card mt-4">
                <div class="card-header"><h5 class="card-title mb-0">{{ translate('messages.catalog_sold_at') }} ({{ $listings->count() }})</h5></div>
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('messages.store') }}</th>
                                <th class="text-right">{{ translate('messages.price') }}</th>
                                <th class="text-right">{{ translate('messages.discount') }}</th>
                                <th class="text-right">{{ translate('messages.stock') }}</th>
                                <th class="text-center">{{ translate('messages.status') }}</th>
                                <th class="text-center">{{ translate('messages.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($listings as $listing)
                                <tr>
                                    <td>{{ $listing->store_name }} <span class="text-muted">#{{ $listing->id }}</span></td>
                                    <td class="text-right">{{ \App\CentralLogics\Helpers::format_currency($listing->price) }}</td>
                                    <td class="text-right">{{ (float) $listing->discount > 0 ? ($listing->discount_type === 'percent' ? $listing->discount . '%' : \App\CentralLogics\Helpers::format_currency($listing->discount)) : '-' }}</td>
                                    <td class="text-right">{{ $listing->stock }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $listing->status ? 'badge-soft-success' : 'badge-soft-danger' }}">
                                            {{ $listing->status ? translate('messages.active') : translate('messages.inactive') }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a class="btn action-btn btn--primary btn-outline-primary" href="{{ route('admin.item.edit', $listing->id) }}" title="{{ translate('messages.catalog_edit_price_stock') }}">
                                            <i class="tio-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center p-4">{{ translate('messages.catalog_no_listings') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($availableStores->isNotEmpty())
                <div class="card mt-4">
                    <div class="card-header d-block">
                        <h5 class="card-title mb-1">{{ translate('messages.catalog_add_to_stores') }}</h5>
                        <p class="text-muted mb-0">{{ translate('messages.catalog_add_to_stores_hint') }}</p>
                    </div>
                    <form method="post" action="{{ route('admin.item.catalog.add-to-stores', $product->id) }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-borderless table-thead-bordered table-align-middle card-table mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="w-40px"></th>
                                        <th>{{ translate('messages.store') }}</th>
                                        <th style="width: 180px;">{{ translate('messages.price') }}</th>
                                        <th style="width: 140px;">{{ translate('messages.stock') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($availableStores as $store)
                                        <tr>
                                            <td><input type="checkbox" name="stores[{{ $store->id }}][selected]" value="1" id="store-{{ $store->id }}"></td>
                                            <td><label for="store-{{ $store->id }}" class="mb-0">{{ $store->name }}</label></td>
                                            <td><input type="number" step="0.01" min="0.01" name="stores[{{ $store->id }}][price]" class="form-control form-control-sm"></td>
                                            <td><input type="number" step="1" min="0" name="stores[{{ $store->id }}][stock]" value="100" class="form-control form-control-sm"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn--primary">{{ translate('messages.catalog_add_to_selected') }}</button>
                        </div>
                    </form>
                </div>
            @endif
        @endif
    </div>
@endsection
