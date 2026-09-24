@extends('backend.layouts.app')

@section('content')
    @php
        use Carbon\Carbon;

    @endphp
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{ translate('Customer Views') }}</h1>
        </div>
    </div>


    <div class="card">
        <form class="" id="sort_customers" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">{{ translate('Views') }}</h5>
                </div>

                <div class="dropdown mb-2 mb-md-0 ml-1">
                    <button type="button" class="btn border menu-dropdown dropdown-toggle" data-toggle="dropdown">
                        {{ translate('Filter') }}
                    </button>
                    <div class="dropdown-menu dropdown-menu-right dropdown-menu-sub-dropdown"
                        onclick="stopPropagation(event)">
                        <div class="px-3 py-2">
                            <div class="d-flex align-items-center">
                                <span class="fs-5 text-dark fw-bold mr-2">{{ translate('Filter Options') }}</span>
                            </div>
                        </div>

                        <div class="separator border-gray-200"></div>

                        <div class="px-3 py-2">
                            <div class="mb-10 py-2">
                                <div class="py-1">
                                    <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                        href="{{ url()->current() . '?' . http_build_query(array_merge(request()->except('products'), ['categories' => true])) }}"
                                        title="{{ translate('Categories') }}">{{ translate('Categories') }}
                                    </a>

                                    <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                        href="{{ url()->current() . '?' . http_build_query(array_merge(request()->except('categories'), ['products' => true])) }}"
                                        title="{{ translate('Products') }}">{{ translate('Products') }}
                                    </a>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">{{ translate('Apply') }}</button>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <input type="text" class="form-control" id="search"
                            name="search"@isset(request()->search) value="{{ request()->search }}" @endisset
                            placeholder="{{ translate('Type name & Enter') }}">
                    </div>
                </div>
            </div>

            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Views Count') }}</th>
                            <th>{{ translate('Last View At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($userViews as $view)
                            <tr>
                                <td>
                                    @if ($view->viewable instanceof App\Models\Product)
                                        {{ translate('Product') }}
                                    @elseif ($view->viewable instanceof App\Models\Category)
                                        {{ translate('Category') }}
                                    @else
                                        {{ translate('Unknown') }}
                                    @endif
                                </td>
                                <td>
                                    @if ($view->viewable instanceof App\Models\Product)
                                        <a href="{{ route('product', $view->viewable->slug) }}">
                                            {{ $view->viewable->name }}
                                        </a>
                                    @elseif ($view->viewable instanceof App\Models\Category)
                                        <a href="{{ route('categories.show', $view->viewable->id) }}">
                                            {{ $view->viewable->name }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $view->view_count }}</td>
                                <td>{{ $view->last_viewed_at }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination">
                    {{ $userViews->appends(request()->input())->links() }}
                </div>
            </div>
        </form>
    </div>
@endsection
