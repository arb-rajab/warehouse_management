@extends('shop.seller.layouts.app')

@section('panel_content')
    <div class="aiz-titlebar mt-2 mb-4">
      <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3">{{ __('seller.common.digital_products') }}</h1>
        </div>
      </div>
    </div>

    <div class="row gutters-10 justify-content-center">
        @if (addon_is_activated('seller_subscription'))
            <div class="col-md-4 mx-auto mb-3" >
                <div class="bg-grad-1 text-white rounded-lg overflow-hidden">
                  <span class="size-30px rounded-circle mx-auto bg-soft-primary d-flex align-items-center justify-content-center mt-3">
                      <i class="las la-upload la-2x text-white"></i>
                  </span>
                  <div class="px-3 pt-3 pb-3">
                      <div class="h4 fw-700 text-center">{{ max(0, auth()->user()->shop->product_upload_limit - auth()->user()->products()->count()) }}</div>
                      <div class="opacity-50 text-center">{{  __('storefront.upload.remaining_uploads') }}</div>
                  </div>
                </div>
            </div>
        @endif


        <div class="col-md-4 mx-auto mb-3" >
            <a href="{{ route('seller.digitalproducts.create')}}">
              <div class="p-3 rounded mb-3 c-pointer text-center bg-white shadow-sm hov-shadow-lg has-transition">
                  <span class="size-60px rounded-circle mx-auto bg-secondary d-flex align-items-center justify-content-center mb-3">
                      <i class="las la-plus la-3x text-white"></i>
                  </span>
                  <div class="fs-18 text-primary">{{ __('seller.products.add_new_digital_product') }}</div>
              </div>
            </a>
        </div>

        @if (addon_is_activated('seller_subscription'))
        @php
            $seller_package = \App\Models\SellerPackage::find(Auth::user()->shop->seller_package_id);
        @endphp
            <div class="col-md-4">
                <a href="{{ route('seller.seller_packages_list') }}" class="text-center bg-white shadow-sm hov-shadow-lg text-center d-block p-3 rounded">
                    @if($seller_package != null)
                        <img src="{{ uploaded_asset($seller_package->logo) }}" height="44" class="mw-100 mx-auto">
                        <span class="d-block sub-title mb-2">{{ __('storefront.common.current_package')}}: {{ $seller_package->getTranslation('name') }}</span>
                    @else
                        <i class="la la-frown-o mb-2 la-3x"></i>
                        <div class="d-block sub-title mb-2">{{ __('storefront.common.no_package_found')}}</div>
                    @endif
                    <div class="btn btn-outline-primary py-1">{{ __('storefront.common.upgrade_package')}}</div>
                </a>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <div class="col text-center text-md-left">
                <h5 class="mb-md-0 h6">{{ __('storefront.product.all_products') }}</h5>
            </div>
        </div>
        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th width="30%">{{ __('storefront.common.name')}}</th>
                        <th data-breakpoints="md">{{ __('storefront.product.category')}}</th>
                        <th data-breakpoints="md">{{ __('storefront.product.base_price')}}</th>
                        <th data-breakpoints="md">{{ __('storefront.common.published')}}</th>
                        <th data-breakpoints="md">{{ __('seller.products.featured')}}</th>
                        <th class="text-right">{{ __('storefront.common.options')}}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($products as $key => $product)
                        <tr>
                            <td>{{ $key+1 }}</td>
                            <td><a href="{{ \App\Support\StorefrontUrl::to($product) }}" target="_blank">{{   $product->getTranslation('name')  }}</a></td>
                            <td>
                                @if ($product->category != null)
                                    {{ $product->category->getTranslation('name') }}
                                @endif
                            </td>
                            <td>{{ $product->unit_price }}</td>
                            <td><label class="aiz-switch aiz-switch-success mb-0">
                                <input onchange="update_published(this)" value="{{ $product->id }}" type="checkbox" <?php if($product->published == 1) echo "checked";?> >
                                <span class="slider round"></span></label>
                            </td>
                            <td><label class="aiz-switch aiz-switch-success mb-0">
                                <input onchange="update_featured(this)" value="{{ $product->id }}" type="checkbox" <?php if($product->featured == 1) echo "checked";?> >
                                <span class="slider round"></span></label>
                            </td>
                            <td class="text-right">
                                <a href="{{route('seller.digitalproducts.edit',  ['id'=>$product->id, 'lang'=>config('app.default_language')] )}}" class="btn btn-soft-info btn-icon btn-circle btn-sm" title="{{ __('storefront.common.edit') }}">
                                  <i class="las la-edit"></i>
                                </a>
                                <a class="btn btn-soft-success btn-icon btn-circle btn-sm" href="{{route('seller.digitalproducts.download', encrypt($product->id))}}" title="{{ __('storefront.common.download') }}">
                                    <i class="las la-download"></i>
                                </a>
                                <a href="javascript:void(0)" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-method="post" data-href="{{route('seller.digitalproducts.destroy', $product->id)}}" title="{{ __('storefront.common.delete') }}">
                                  <i class="las la-trash"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="aiz-pagination">
                {{ $products->links() }}
          	</div>
        </div>
    </div>

@endsection

@section('modal')
    @include('shop.modals.delete_modal')
@endsection

@section('script')
    <script type="text/javascript">
        function update_featured(el){
            if(el.checked){
                var status = 1;
            }
            else{
                var status = 0;
            }
            $.post('{{ route('seller.products.featured') }}', {_token:'{{ csrf_token() }}', id:el.value, status:status}, function(data){
                if(data == 1){
                    AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('seller.products.featured_products_updated_successfully')) }});
                }
                else{
                    AIZ.plugins.notify('danger', {{ \Illuminate\Support\Js::from(__('storefront.common.something_went_wrong_label')) }});
                    location.reload();
                }
            });
        }

        function update_published(el){
            if(el.checked){
                var status = 1;
            }
            else{
                var status = 0;
            }
            $.post('{{ route('seller.products.published') }}', {_token:'{{ csrf_token() }}', id:el.value, status:status}, function(data){
                if(data == 1){
                    AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('seller.products.published_products_updated_successfully')) }});
                }
                else if(data == 2){
                    AIZ.plugins.notify('danger', {{ \Illuminate\Support\Js::from(__('seller.products.please_upgrade_your_package')) }});
                    location.reload();
                }
                else{
                    AIZ.plugins.notify('danger', {{ \Illuminate\Support\Js::from(__('storefront.common.something_went_wrong_label')) }});
                    location.reload();
                }
            });
        }
    </script>
@endsection
