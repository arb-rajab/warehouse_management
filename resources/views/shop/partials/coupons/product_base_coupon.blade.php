<div class="card-header mb-2">
    <h3 class="h6">{{__('seller.coupons.add_your_product_base_coupon')}}</h3>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-from-label" for="code">{{__('seller.coupons.coupon_code')}}</label>
    <div class="col-lg-9">
        <input type="text" placeholder="{{__('seller.coupons.coupon_code')}}" id="code" name="code" class="form-control" required>
    </div>
</div>
<div class="product-choose-list">
    <div class="product-choose">
        <div class="form-group row">
            <label class="col-lg-3 col-from-label" for="name">{{__('storefront.product.product')}}</label>
            <div class="col-lg-9">
                <select name="product_ids[]" class="form-control product_id aiz-selectpicker" data-live-search="true" data-selected-text-format="count" required multiple>
                    @foreach($products as $product)
                        <option value="{{$product->id}}">{{ $product->getTranslation('name') }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
<br>
<div class="form-group row">
    <label class="col-sm-3 control-label" for="start_date">{{__('storefront.common.date')}}</label>
    <div class="col-sm-9">
      <input type="text" class="form-control aiz-date-range" name="date_range" placeholder="{{ __('seller.coupons.select_date') }}">
    </div>
</div>
<div class="form-group row">
   <label class="col-lg-3 col-from-label">{{__('seller.coupons.discount')}}</label>
   <div class="col-lg-7">
      <input type="number" lang="en" min="0" step="0.01" placeholder="{{__('seller.coupons.discount')}}" name="discount" class="form-control" required>
   </div>
   <div class="col-lg-2">
       <select class="form-control aiz-selectpicker" name="discount_type">
           <option value="amount">{{__('storefront.common.amount')}}</option>
           <option value="percent">{{__('seller.products.percent')}}</option>
       </select>
   </div>
</div>


<script type="text/javascript">

    $(document).ready(function(){
        $('.aiz-date-range').daterangepicker();
        AIZ.plugins.bootstrapSelect('refresh');
    });

</script>
