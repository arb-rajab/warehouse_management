@extends('shop.seller.layouts.app')

@section('panel_content')
<h1 class="h3">{{ translate('Add Your Product') }}</h1>
<form action="{{ route('seller.digitalproducts.store') }}" method="POST" enctype="multipart/form-data" id="digital-product-form">
    @csrf
    @include('shop.seller.product.digitalproducts.form_fields')
    <button type="submit" class="btn btn-primary">{{ translate('Save product') }}</button>
</form>
@endsection

@section('script')
<script src="{{ static_asset('assets/js/seller-digital-product-form.js') }}" defer></script>
@endsection
