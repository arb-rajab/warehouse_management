<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class ProductRequest extends FormRequest
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
        $rules = [];

        $rules['name']          = 'required|max:255';
        $rules['category_id']   = 'required';
        $rules['unit' ]         = 'required';
        $rules['min_qty' ]      = 'required|numeric';
        $rules['unit_price']    = 'required|numeric';
        if ($this->get('discount') != null) {
            $rules['discount'] = 'required';
        }
        if ($this->get('discount_type') == 'amount') {
            $rules['discount' ] = 'required|numeric|lt:unit_price';
        }elseif($this->get('discount_type') == 'percent') {
            $rules['discount' ] = 'required|numeric|lt:100';
        }
        // $rules['current_stock'] = 'required|numeric';

        return $rules;
    }

    /**
     * Get the validation messages of rules that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required'             => __('validation.required', ['attribute' => __('validation.attributes.name')]),
            'category_id.required'      => __('validation.required', ['attribute' => __('validation.attributes.category_id')]),
            'unit.required'             => __('validation.required', ['attribute' => __('validation.attributes.unit')]),
            'min_qty.required'          => __('validation.required', ['attribute' => __('validation.attributes.min_qty')]),
            'min_qty.numeric'           => __('validation.numeric', ['attribute' => __('validation.attributes.min_qty')]),
            'unit_price.required'       => __('validation.required', ['attribute' => __('validation.attributes.unit_price')]),
            'unit_price.numeric'        => __('validation.numeric', ['attribute' => __('validation.attributes.unit_price')]),
            'discount.required'         => __('validation.required', ['attribute' => __('validation.attributes.discount')]),
            'discount.numeric'          => __('validation.numeric', ['attribute' => __('validation.attributes.discount')]),
            'discount.lt:unit_price'    => __('api.product.discount_below_price'),
            'current_stock.required'    => __('validation.required', ['attribute' => __('validation.attributes.current_stock')]),
            'current_stock.numeric'     => __('validation.numeric', ['attribute' => __('validation.attributes.current_stock')]),
        ];
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
