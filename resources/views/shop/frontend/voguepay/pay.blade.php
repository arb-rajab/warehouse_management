<script src="//pay.voguepay.com/js/voguepay.js"></script>

<script>
    // route() URLs with a placeholder for the transaction id: the store lives under /temp, which a
    // hand-built config('app.url') + '/vogue-pay/...' did not carry.
    var successUrl = {{ \Illuminate\Support\Js::from($successUrl) }};
    var failureUrl = {{ \Illuminate\Support\Js::from($failureUrl) }};

    closedFunction = function () {
        alert({{ \Illuminate\Support\Js::from(__('storefront.checkout.window_closed')) }});
        location.href = {{ \Illuminate\Support\Js::from($homeUrl) }};
    };

    successFunction = function (transaction_id) {
        location.href = successUrl.replace('__id__', encodeURIComponent(transaction_id));
    };

    failedFunction = function (transaction_id) {
        location.href = failureUrl.replace('__id__', encodeURIComponent(transaction_id));
    };

    window.onload = function () {
        Voguepay.init({
            v_merchant_id: {{ \Illuminate\Support\Js::from($merchantId) }},
            total: {{ \Illuminate\Support\Js::from($total) }},
            cur: {{ \Illuminate\Support\Js::from($currency) }},
            merchant_ref: {{ \Illuminate\Support\Js::from($merchantRef) }},
            memo: {{ \Illuminate\Support\Js::from(config('app.name').' payment') }},
            store_id: 1,
            customer: {
                name: {{ \Illuminate\Support\Js::from((string) $payer->name) }},
                address: {{ \Illuminate\Support\Js::from((string) $payer->address) }},
                city: {{ \Illuminate\Support\Js::from((string) $payer->city) }},
                state: '',
                zipcode: {{ \Illuminate\Support\Js::from((string) $payer->postal_code) }},
                email: {{ \Illuminate\Support\Js::from((string) $payer->email) }},
                phone: {{ \Illuminate\Support\Js::from((string) $payer->phone) }}
            },
            closed: closedFunction,
            success: successFunction,
            failed: failedFunction
        });
    };
</script>
