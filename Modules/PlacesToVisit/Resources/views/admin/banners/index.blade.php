@extends('layouts.admin.app')

@section('title', translate('messages.place_banners'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-image"></i> {{ translate('messages.place_banners') }}
                </h1>
            </div>
            <div class="col-sm-auto">
                <a class="btn btn-primary" href="{{ route('admin.places.banners.create') }}">
                    <i class="tio-add"></i> {{ translate('messages.add_banner') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row gx-2" action="{{ route('admin.places.banners.index') }}" method="get">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" 
                           placeholder="{{ translate('messages.search_by_title') }}" 
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-control">
                        <option value="">{{ translate('messages.all_types') }}</option>
                        <option value="default" {{ request('type') == 'default' ? 'selected' : '' }}>{{ translate('messages.default') }}</option>
                        <option value="category" {{ request('type') == 'category' ? 'selected' : '' }}>{{ translate('messages.category') }}</option>
                        <option value="place" {{ request('type') == 'place' ? 'selected' : '' }}>{{ translate('messages.place') }}</option>
                        <option value="external" {{ request('type') == 'external' ? 'selected' : '' }}>{{ translate('messages.external') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control">
                        <option value="">{{ translate('messages.all_status') }}</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ translate('messages.active') }}</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ translate('messages.inactive') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">{{ translate('messages.filter') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.sl') }}</th>
                            <th>{{ translate('messages.image') }}</th>
                            <th>{{ translate('messages.title') }}</th>
                            <th>{{ translate('messages.type') }}</th>
                            <th>{{ translate('messages.zone') }}</th>
                            <th>{{ translate('messages.priority') }}</th>
                            <th>{{ translate('messages.validity') }}</th>
                            <th>{{ translate('messages.featured') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($banners as $key => $banner)
                        <tr>
                            <td>{{ $banners->firstItem() + $key }}</td>
                            <td>
                                <img src="{{ asset('storage/app/public/place_banner/' . $banner->image) }}" 
                                     onerror="this.src='{{ asset('public/assets/admin/img/400x400/img2.jpg') }}'"
                                     class="rounded" width="80" height="45" style="object-fit: cover;">
                            </td>
                            <td>
                                <strong>{{ $banner->title }}</strong>
                                @if($banner->title_ar)
                                <br><small class="text-muted" dir="rtl">{{ $banner->title_ar }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-soft-info">{{ ucfirst($banner->type) }}</span>
                            </td>
                            <td>{{ $banner->zone?->name ?? translate('messages.all_zones') }}</td>
                            <td>{{ $banner->priority }}</td>
                            <td>
                                @if($banner->start_date || $banner->end_date)
                                    <small>
                                        {{ $banner->start_date ? $banner->start_date->format('M d, Y') : '-' }}
                                        <br>to<br>
                                        {{ $banner->end_date ? $banner->end_date->format('M d, Y') : '-' }}
                                    </small>
                                @else
                                    <span class="text-muted">{{ translate('messages.always') }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.places.banners.toggle-featured', $banner->id) }}"
                                   class="badge badge-soft-{{ $banner->is_featured ? 'warning' : 'secondary' }}">
                                    {{ $banner->is_featured ? translate('messages.featured') : translate('messages.normal') }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('admin.places.banners.toggle-status', $banner->id) }}"
                                   class="badge badge-soft-{{ $banner->is_active ? 'success' : 'danger' }}">
                                    {{ $banner->is_active ? translate('messages.active') : translate('messages.inactive') }}
                                </a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.places.banners.edit', $banner->id) }}"
                                   class="btn btn-sm btn-white">
                                    <i class="tio-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-white" 
                                        onclick="if(confirm('{{ translate('messages.are_you_sure') }}')){document.getElementById('delete-{{ $banner->id }}').submit()}">
                                    <i class="tio-delete text-danger"></i>
                                </button>
                                <form id="delete-{{ $banner->id }}" 
                                      action="{{ route('admin.places.banners.destroy', $banner->id) }}" 
                                      method="POST" style="display:none">
                                    @csrf @method('DELETE')
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                {{ translate('messages.no_data_found') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $banners->links() }}
        </div>
    </div>
</div>
@endsection
