@extends('layouts.admin.app')

@section('title', translate('messages.catalogue'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap __gap-15px justify-content-between align-items-center">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/items.png') }}" class="w--22" alt="">
                </span>
                <span>{{ translate('messages.catalogue') }} <span class="badge badge-soft-dark ml-2">{{ $products->total() }}</span></span>
            </h1>
            <a href="{{ route('admin.item.catalog.create') }}" class="btn btn--primary">
                <i class="tio-add"></i> {{ translate('messages.catalog_new_product') }}
            </a>
        </div>

        <p class="text-muted mb-3">{{ translate('messages.catalog_index_hint') }}</p>

        @if ($driftedCount > 0)
            <div class="alert alert-soft-warning d-flex align-items-center justify-content-between flex-wrap __gap-12px mb-3" role="alert">
                <span>{{ translate('messages.catalog_drifted_notice', ['count' => $driftedCount]) }}</span>
                <a href="{{ route('admin.item.catalog.index', ['drifted' => 1]) }}" class="btn btn-sm btn-warning">{{ translate('messages.show') }}</a>
            </div>
        @endif

        <div class="card">
            <div class="card-header py-2 border-0">
                <form class="search-form w-100" method="get">
                    <div class="input-group input--group">
                        <input type="search" name="search" value="{{ $search }}" class="form-control h--40px"
                               placeholder="{{ translate('messages.catalog_search_placeholder') }}">
                        @if (request('drifted'))
                            <input type="hidden" name="drifted" value="1">
                        @endif
                        <button type="submit" class="btn btn--secondary h--40px"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.product') }}</th>
                            <th>{{ translate('messages.category') }}</th>
                            <th>{{ translate('messages.barcode') }}</th>
                            <th class="text-center">{{ translate('messages.stores') }}</th>
                            <th class="text-right">{{ translate('messages.catalog_from_price') }}</th>
                            <th class="text-center">{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.item.catalog.edit', $product->id) }}" class="media align-items-center">
                                        <img class="avatar avatar-lg mr-3 onerror-image" src="{{ $product->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="">
                                        <div class="media-body">
                                            <h5 class="text-hover-primary mb-0">{{ Str::limit($product->name, 40) }}</h5>
                                            @if ($product->last_propagated_at === null || $product->updated_at > $product->last_propagated_at)
                                                @if ($product->listings_count > 0)
                                                    <span class="badge badge-soft-warning">{{ translate('messages.catalog_drifted') }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    </a>
                                </td>
                                <td>{{ Str::limit($product->category?->name ?? '-', 24) }}</td>
                                <td>{{ $product->barcode ?? '-' }}</td>
                                <td class="text-center">{{ $product->listings_count }}</td>
                                <td class="text-right">{{ $product->listings_min_price !== null ? \App\CentralLogics\Helpers::format_currency($product->listings_min_price) : '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $product->status ? 'badge-soft-success' : 'badge-soft-danger' }}">
                                        {{ $product->status ? translate('messages.active') : translate('messages.inactive') }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a class="btn action-btn btn--primary btn-outline-primary" href="{{ route('admin.item.catalog.edit', $product->id) }}" title="{{ translate('messages.edit') }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center p-4">{{ translate('messages.no_data_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer">{!! $products->links() !!}</div>
        </div>
    </div>
@endsection
