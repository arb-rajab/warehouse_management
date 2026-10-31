<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttributeValueRequest extends FormRequest
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
        return [
            'attribute_id'  => 'required',
            'value'         => ['required', 'max:255', Rule::unique('attribute_values')->ignore($this->attribute_value)],
            'color_code'    => ['required_if:type,color','max:255', Rule::unique('attribute_values')->ignore($this->attribute_value)],
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
            'attribute_id.required'             => __('store_admin.attribute_is_required'),
            'value.required'                    => __('store_admin.attribute_value_is_required'),
            'value.max'                         => __('store_admin.max_255_characters_for_attribute_value'),
            'color_code.required_if:type'       => __('store_admin.color_code_is_required'),
        ];
    }
}
