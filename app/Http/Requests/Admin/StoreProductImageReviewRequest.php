<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesProductImageReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageReviewRequest extends FormRequest
{
    use ValidatesProductImageReview;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->imageReviewRules();
    }
}
