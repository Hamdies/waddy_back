@extends('layouts.admin.app')

@section('title', translate('messages.place_categories'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-category"></i> {{ translate('messages.place_categories') }}
                </h1>
            </div>
            <div class="col-sm-auto">
                <a class="btn btn-primary" href="{{ route('admin.places.categories.create') }}">
                    <i class="tio-add"></i> {{ translate('messages.add_category') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-header">
            <form class="row gx-2" action="{{ route('admin.places.categories.index') }}" method="get">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" 
                           placeholder="{{ translate('messages.search_by_name') }}" 
                           value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">{{ translate('messages.search') }}</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.sl') }}</th>
                            <th>{{ translate('messages.image') }}</th>
                            <th>{{ translate('messages.name') }}</th>
                            <th>{{ translate('messages.priority') }}</th>
                            <th>{{ translate('messages.places_count') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $key => $category)
                        <tr>
                            <td>{{ $categories->firstItem() + $key }}</td>
                            <td>
                                <img src="{{ asset('storage/app/public/place-category/' . $category->image) }}" 
                                     onerror="this.src='{{ asset('public/assets/admin/img/160x160/img1.jpg') }}'"
                                     class="rounded" width="60">
                            </td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->priority }}</td>
                            <td>{{ $category->places()->count() }}</td>
                            <td>
                                <a href="{{ route('admin.places.categories.toggle-status', $category->id) }}"
                                   class="badge badge-soft-{{ $category->is_active ? 'success' : 'danger' }}">
                                    {{ $category->is_active ? translate('messages.active') : translate('messages.inactive') }}
                                </a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.places.categories.edit', $category->id) }}"
                                   class="btn btn-sm btn-white">
                                    <i class="tio-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-white" 
                                        onclick="if(confirm('{{ translate('messages.are_you_sure') }}')){document.getElementById('delete-{{ $category->id }}').submit()}">
                                    <i class="tio-delete text-danger"></i>
                                </button>
                                <form id="delete-{{ $category->id }}" 
                                      action="{{ route('admin.places.categories.destroy', $category->id) }}" 
                                      method="POST" style="display:none">
                                    @csrf @method('DELETE')
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                {{ translate('messages.no_data_found') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $categories->links() }}
        </div>
    </div>
</div>
@endsection
