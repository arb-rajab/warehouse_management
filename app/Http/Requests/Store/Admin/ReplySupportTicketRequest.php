<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesSupportReplies;
use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReplySupportTicketRequest extends FormRequest
{
    use ValidatesSupportReplies;
    use ValidatesWebsiteSettings;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['ticket_id' => ['required', 'integer', Rule::exists('tickets', 'id')], 'reply' => $this->supportReplyRules(), 'status' => ['required', Rule::in(['pending', 'open', 'solved'])], 'attachments' => $this->uploadIdsRules('image')];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (is_string($this->input('reply')) && trim(strip_tags(clean_html($this->input('reply')))) === '') {
                $validator->errors()->add('reply', __('validation.required', ['attribute' => 'reply']));
            }
        });
    }
}
