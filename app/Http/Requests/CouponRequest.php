<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $productsRule           = 'sometimes';
        $minBuyRule             = 'sometimes';
        $maxDiscountRule        = 'sometimes';
        if ($this->request->get('type') == 'product_base') {
            $productsRule       = 'required';
        }
        if ($this->request->get('type') == 'cart_base') {
            $minBuyRule         = ['required', 'numeric', 'min:1'];
            $maxDiscountRule    = ['required', 'numeric', 'min:1'];
        }
        return [
            'type'          => ['required'],
            'code'          => ['required', Rule::unique('coupons')->ignore($this->coupon), 'max:255',],
            'discount'      => ['required', 'numeric', 'min:1'],
            'discount_type' => ['required'],
            'product_ids'   => $productsRule,
            'min_buy'       => $minBuyRule,
            'max_discount'  => $maxDiscountRule,
            'date_range'    => ['required'],
            'start_date'    => ['required'],
            'end_date'      => ['required'],
            'details'       => ['required'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'type.required'         => __('seller.coupons.coupon_type_is_required'),
            'code.required'         => __('seller.coupons.coupon_code_is_required'),
            'code.unique'           => __('seller.coupons.coupon_already_exist_for_this_coupon_code'),
            'code.max'              => __('seller.coupons.max_255_characters'),
            'product_ids.required'  => __('seller.coupons.product_is_required'),
            'discount.required'     => __('seller.coupons.discount_is_required'),
            'discount.numeric'      => __('seller.coupons.discount_should_be_numeric_type'),
            'discount.min'          => __('seller.coupons.discount_should_be_l_or_greater'),
            'discount_type.required' => __('seller.coupons.discount_type_is_required'),
            'min_buy.required'      => __('seller.coupons.minimum_shopping_amount_is_required'),
            'min_buy.numeric'       => __('seller.coupons.minimum_shopping_amount_should_be_numeric_type'),
            'min_buy.min'           => __('seller.coupons.minimum_shopping_amount_should_be_l_or_greater'),
            'max_discount.required' => __('seller.coupons.max_discount_amount_is_required'),
            'max_discount.numeric'  => __('seller.coupons.max_discount_amount_should_be_numeric_type'),
            'max_discount.min'      => __('seller.coupons.max_discount_amount_should_be_l_or_greater'),
            'date_range.required'   => __('seller.coupons.date_range_is_required'),
        ];
    }


    protected function prepareForValidation()
    {
        $date_range = explode(" - ", $this->date_range);
        $start_date = '';
        $end_date = '';
        // dd($date_range);
        if($date_range[0]) {
            $start_date = strtotime($date_range[0]);
            $end_date = strtotime($date_range[1]);
        }
        $coupon_details = null;
        if ($this->type == "product_base") {
            $coupon_details = [];
            if($this->product_ids) {
                foreach ($this->product_ids as $product_id) {
                    $data['product_id'] = $product_id;
                    array_push($coupon_details, $data);
                }
            }
            $coupon_details = json_encode($coupon_details);
        } elseif ($this->type == "cart_base") {
            $data                     = [];
            $data['min_buy']          = $this->min_buy;
            $data['max_discount']     = $this->max_discount;
            $coupon_details           = json_encode($data);
        }

        $this->merge([
            'start_date'    => $start_date,
            'end_date'      => $end_date,
            'details'       => $coupon_details
        ]);
    }

    /**
     * Get the error messages for the defined validation rules.*
     * @return array
     */
    public function failedValidation(Validator $validator)
    {
        // dd($this->expectsJson());
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => $validator->errors()->all(),
                'result' => false
            ], 422));
        } else {
            throw (new ValidationException($validator))
                    ->errorBag($this->errorBag)
                    ->redirectTo($this->getRedirectUrl());
        }
    }
}
