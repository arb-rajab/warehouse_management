<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesSupportReplies;
use Illuminate\Foundation\Http\FormRequest;

class ReplyProductQueryRequest extends FormRequest
{
    use ValidatesSupportReplies;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reply' => $this->supportReplyRules()];
    }
}
