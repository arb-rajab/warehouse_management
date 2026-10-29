@extends('shop.seller.layouts.app')

@section('panel_content')

    <div class="aiz-titlebar mt-2 mb-4">
      <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3">{{ __('seller.products.bulk_products_upload') }}</h1>
        </div>
      </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table aiz-table mb-0" style="font-size:14px; background-color: #cce5ff; border-color: #b8daff">
                <tr>
                    <td>{{ __('seller.products.bulk_download_template')}}:</td>
                </tr>
                <tr >
                    <td>{{ __('seller.products.bulk_example_help')}}:</td>
                </tr>
                <tr>
                    <td>{{ __('seller.products.bulk_upload_help')}}:</td>
                </tr>
                <tr>
                    <td>{{ __('seller.products.bulk_complete_products')}}</td>
                </tr>
            </table>
            <a href="{{ static_asset('download/product_bulk_demo.xlsx') }}" download><button class="btn btn-primary mt-2">{{ __('seller.products.download_csv') }}</button></a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table aiz-table mb-0" style="font-size:14px;background-color: #cce5ff;border-color: #b8daff">
                <tr>
                    <td>{{ __('seller.products.bulk_identifiers_help')}}:</td>
                </tr>
                <tr >
                    <td>{{ __('seller.products.bulk_identifier_download_help')}}:</td>
                </tr>
            </table>
            <a href="{{ route('seller.pdf.download_category') }}"><button class="btn btn-primary mt-2">{{ __('seller.products.download_category')}}</button></a>
            <a href="{{ route('seller.pdf.download_brand') }}"><button class="btn btn-primary mt-2">{{ __('seller.products.download_brand')}}</button></a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="col text-center text-md-left">
                <h5 class="mb-md-0 h6">{{ __('seller.products.upload_csv_file') }}</h5>
            </div>
        </div>
        <div class="card-body">
            <form class="form-horizontal" action="{{ route('seller.bulk_product_upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <x-required-legend />
                <div class="form-group row">
                    <label class="col-md-2 col-form-label" for="bulk-file">{{ __('seller.products.csv') }}<x-required-mark /></label>
                    <div class="col-sm-10">
                        <div class="custom-file">
                            <div class="custom-file-label">
                                <input type="file" id="bulk-file" name="bulk_file" accept=".xlsx,.xls,.csv,.txt" class="custom-file-input" required>
                                <span class="custom-file-name">{{ __('storefront.upload.choose_file')}}</span>
                            </div>
                        </div>
                    @error('bulk_file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-primary">{{__('seller.products.upload_csv')}}</button>
                </div>
            </form>
        </div>
    </div>

@endsection
