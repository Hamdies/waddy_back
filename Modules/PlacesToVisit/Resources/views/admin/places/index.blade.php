@extends('layouts.admin.app')

@section('title', translate('messages.places'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-poi"></i> {{ translate('messages.places') }}
                </h1>
            </div>
            <div class="col-sm-auto">
                <a class="btn btn-primary" href="{{ route('admin.places.create') }}">
                    <i class="tio-add"></i> {{ translate('messages.add_place') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row gx-2" action="{{ route('admin.places.index') }}" method="get">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" 
                           placeholder="{{ translate('messages.search_by_title') }}" 
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="category_id" class="form-control">
                        <option value="">{{ translate('messages.all_categories') }}</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                        @endforeach
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
                            <th>{{ translate('messages.category') }}</th>
                            <th>{{ translate('messages.votes') }}</th>
                            <th>{{ translate('messages.rating') }}</th>
                            <th>{{ translate('messages.featured') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($places as $key => $place)
                        <tr>
                            <td>{{ $places->firstItem() + $key }}</td>
                            <td>
                                <img src="{{ $place->image }}" 
                                     onerror="this.src='{{ asset('public/assets/admin/img/160x160/img1.jpg') }}'"
                                     class="rounded" width="60">
                            </td>
                            <td>
                                <strong>{{ $place->title }}</strong>
                                <br><small class="text-muted">{{ Str::limit($place->address, 30) }}</small>
                            </td>
                            <td>{{ $place->category?->name ?? '-' }}</td>
                            <td>
                                <span class="badge badge-soft-primary">
                                    {{ $place->votes_count ?? 0 }} {{ translate('messages.votes') }}
                                </span>
                            </td>
                            <td>
                                @if($place->votes_avg_rating)
                                    <i class="tio-star text-warning"></i> {{ number_format($place->votes_avg_rating, 1) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.places.toggle-featured', $place->id) }}"
                                   class="badge badge-soft-{{ $place->is_featured ? 'warning' : 'secondary' }}">
                                    {{ $place->is_featured ? translate('messages.featured') : translate('messages.normal') }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('admin.places.toggle-status', $place->id) }}"
                                   class="badge badge-soft-{{ $place->is_active ? 'success' : 'danger' }}">
                                    {{ $place->is_active ? translate('messages.active') : translate('messages.inactive') }}
                                </a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.places.edit', $place->id) }}"
                                   class="btn btn-sm btn-white">
                                    <i class="tio-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-white" 
                                        onclick="if(confirm('{{ translate('messages.are_you_sure') }}')){document.getElementById('delete-{{ $place->id }}').submit()}">
                                    <i class="tio-delete text-danger"></i>
                                </button>
                                <form id="delete-{{ $place->id }}" 
                                      action="{{ route('admin.places.destroy', $place->id) }}" 
                                      method="POST" style="display:none">
                                    @csrf @method('DELETE')
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                {{ translate('messages.no_data_found') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $places->links() }}
        </div>
    </div>
</div>
@endsection
