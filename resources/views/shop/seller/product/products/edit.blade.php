@extends('shop.seller.layouts.app')

@section('panel_content')
@php($editing = isset($product))
<h1 class="h3">{{ __('seller.products.update_your_product') }}</h1>
<form action="{{ route('seller.products.update', $product->id) }}" method="POST" enctype="multipart/form-data" id="choice_form">
    @csrf
    <x-required-legend />
    @if ($errors->any())
        <div class="alert alert-danger" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @include('shop.seller.product.products.form_fields')
    <button type="submit"  class="btn btn-primary">{{ __('storefront.product.save_product') }}</button>
</form>
@endsection

@include('shop.seller.product.products.form_script')
