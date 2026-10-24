<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesOptionalNote;
use App\Http\Requests\Concerns\ValidatesPalletContents;
use App\Http\Requests\Concerns\ValidatesProductImageReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePalletRequest extends FormRequest
{
    use ValidatesOptionalNote;
    use ValidatesPalletContents;
    use ValidatesProductImageReview;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->noteRules(),
            ...$this->imageReviewRules(),
            ...$this->palletContentsRules(),
        ];
    }
}
