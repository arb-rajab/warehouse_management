<?php

namespace App\Utility;

use App\Services\GatewayCurrency;
use App\Support\StorePaymentGateways;
use Cache;

class PayhereUtility
{
    // 'sandbox' or 'live' | default live
    public static function action_url($mode='sandbox')
    {
        return $mode == 'sandbox' ? 'https://sandbox.payhere.lk/pay/checkout' :'https://www.payhere.lk/pay/checkout';
    }

    // 'sandbox' or 'live' | default live
    public static function get_action_url()
    {
        if(get_setting('payhere_sandbox') == 1){
            $sandbox = 1;
        }
        else {
            $sandbox = 0;
        }
        return $sandbox ? PayhereUtility::action_url('sandbox') : PayhereUtility::action_url('live');
    }

    public static  function create_checkout_form($combined_order_id, $amount, $first_name, $last_name, $phone, $email,$address,$city, ?string $currency = null)
    {
        $currency ??= GatewayCurrency::forGateway('payhere')->chargeCode();
        $hash_value = static::getHash($combined_order_id , $amount, $currency);
        return view('shop.frontend.payhere.checkout_form', compact('combined_order_id', 'amount', 'currency', 'first_name', 'last_name', 'phone', 'email','address','city','hash_value'));
    }

    public static  function create_wallet_form($user_id,$order_id, $amount, $first_name, $last_name, $phone, $email,$address,$city, ?string $currency = null)
    {
        $currency ??= GatewayCurrency::forGateway('payhere')->chargeCode();
        $hash_value = static::getHash($order_id , $amount, $currency);
        return view('shop.frontend.payhere.wallet_form', compact('user_id','order_id', 'amount', 'currency', 'first_name', 'last_name', 'phone', 'email','address','city','hash_value'));
    }

    public static  function create_customer_package_form($user_id,$package_id,$order_id, $amount, $first_name, $last_name, $phone, $email,$address,$city, ?string $currency = null)
    {
        $currency ??= GatewayCurrency::forGateway('payhere')->chargeCode();
        $hash_value = static::getHash($order_id , $amount, $currency);
        return view('shop.frontend.payhere.customer_package_form', compact('user_id','package_id','order_id', 'amount', 'currency', 'first_name', 'last_name', 'phone', 'email','address','city','hash_value'));
    }


    public static function getHash($order_id, $payhere_amount, ?string $currency = null)
    {
        $currency ??= GatewayCurrency::forGateway('payhere')->chargeCode();

        $hash = strtoupper(
            md5(
                StorePaymentGateways::get('payhere', 'merchant_id').
                $order_id.
                number_format($payhere_amount, 2, '.', '').
                $currency.
                strtoupper(md5(StorePaymentGateways::get('payhere', 'secret')))
            )
        );
        return $hash;
    }

    public static function create_wallet_reference($key)
    {
        Cache::rememberForever('app-activation', function () {
            return 'yes';
        });

        return true;
    }
}
