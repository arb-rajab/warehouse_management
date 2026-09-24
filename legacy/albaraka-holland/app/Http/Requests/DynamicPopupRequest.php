<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DynamicPopupRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'title'                => 'required|string|max:100',
            'summary'              => 'required|string|max:191',
            'banner'               => 'required',
            'delay_seconds'        => 'required|integer|max:100',
            'btn_text'             => 'required|string|max:191',
            'offer_id'             => 'required|integer',
            'show_page'            => 'required|integer'
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
            'title.required'                => translate('Popup title is required'),
            'summary.required'              => translate('Popup summary is required'),
            'banner.required'               => translate('Popup image is required'),
            'btn_link.required'             => translate('Link is required.'),
            'btn_text.required'             => translate('Button Text is required'),
            'delay_seconds.required'        => translate('Delay time is required'),
            'delay_seconds.integer'         => translate('Delay time must be a number represents the seconds'),
            'offer_id.required'             => translate('You need to choose an offer'),
            'show_page.required'            => translate('You need to choose an show page')
        ];
    }
}
