@php
    $coupon_det = json_decode($coupon->details);
@endphp

<div class="card-header mb-2">
   <h3 class="h6">{{__('seller.coupons.edit_your_cart_base_coupon')}}</h3>
</div>
<div class="form-group row">
   <label class="col-lg-3 col-from-label" for="code">{{__('seller.coupons.coupon_code')}}</label>
   <div class="col-lg-9">
       <input type="text" value="{{$coupon->code}}" id="code" name="code" class="form-control" required>
   </div>
</div>


<div class="form-group row">
  <label class="col-lg-3 col-from-label">{{__('seller.coupons.minimum_shopping')}}</label>
  <div class="col-lg-9">
     <input type="number" lang="en" min="0" step="0.01" name="min_buy" class="form-control" value="{{ $coupon_det->min_buy }}" required>
  </div>
</div>
<div class="form-group row">
   <label class="col-lg-3 col-from-label">{{__('seller.coupons.discount')}}</label>
   <div class="col-lg-7">
       <input type="number" lang="en" min="0" step="0.01" placeholder="{{__('seller.coupons.discount')}}" name="discount" class="form-control" value="{{ $coupon->discount }}" required>
   </div>
   <div class="col-lg-2">
       <select class="form-control aiz-selectpicker" name="discount_type">
           <option value="amount" @if ($coupon->discount_type == 'amount') selected  @endif >{{__('storefront.common.amount')}}</option>
           <option value="percent" @if ($coupon->discount_type == 'percent') selected  @endif>{{__('seller.products.percent')}}</option>
       </select>
   </div>
</div>
<div class="form-group row">
  <label class="col-lg-3 col-from-label">{{__('seller.coupons.maximum_discount_amount')}}</label>
  <div class="col-lg-9">
     <input type="number" lang="en" min="0" step="0.01" placeholder="{{__('seller.coupons.maximum_discount_amount')}}" name="max_discount" class="form-control" value="{{ $coupon_det->max_discount }}" required>
  </div>
</div>

@php
  $start_date = date('m/d/Y', $coupon->start_date);
  $end_date = date('m/d/Y', $coupon->end_date);
@endphp
<div class="form-group row">
    <label class="col-sm-3 control-label" for="start_date">{{__('storefront.common.date')}}</label>
    <div class="col-sm-9">
      <input type="text" class="form-control aiz-date-range" value="{{ $start_date .' - '. $end_date }}" name="date_range" placeholder="{{ __('seller.coupons.select_date') }}">
    </div>
</div>


<script type="text/javascript">
   $(document).ready(function(){
       $('.aiz-selectpicker').selectpicker();
       $('.aiz-date-range').daterangepicker();
   });

</script>
