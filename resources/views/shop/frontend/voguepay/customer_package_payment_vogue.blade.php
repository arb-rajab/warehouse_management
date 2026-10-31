<script src="//pay.voguepay.com/js/voguepay.js"></script>

<script>
    closedFunction=function() {
        alert({{ \Illuminate\Support\Js::from(__('storefront.checkout.window_closed')) }});
        location.href = '{{ config('app.url') }}'
    }

    successFunction=function(transaction_id) {
        location.href = '{{ config('app.url') }}'+'/vogue-pay/success/'+transaction_id
    }
    failedFunction=function(transaction_id) {
         location.href = '{{ config('app.url') }}'+'/vogue-pay/success/'+transaction_id
    }
</script>
@if (get_setting('voguepay_sandbox') == 1)
    <input type="hidden" id="merchant_id" name="v_merchant_id" value="demo">
@else
    <input type="hidden" id="merchant_id" name="v_merchant_id" value="{{ env('VOGUE_MERCHANT_ID') }}">
@endif
@php
    $customer_package = \App\Models\CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
@endphp

<script>

        window.onload = function(){
            pay3();
        }

        function pay3() {
         Voguepay.init({
             v_merchant_id: document.getElementById("merchant_id").value,
             total: '{{ $customer_package->amount }}',
             cur: '{{\App\Models\Currency::findOrFail(get_setting('system_default_currency'))->code}}',
             merchant_ref: '{{ Session::get('voguepay_merchant_ref') }}',
             loadText:'Custom load text',
             customer: {
                name: '{{ Auth::user()->name }}',

                email: '{{ Auth::user()->email }}',
                phone: '{{ Auth::user()->phone }}'
            },
             closed:closedFunction,
             success:successFunction,
             failed:failedFunction
         });
        }
</script>
