<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Models\Conversation;
use App\Policies\ConversationPolicy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    public function authorize(): bool
    {
        $id = filter_var($this->input('conversation_id'), FILTER_VALIDATE_INT);

        return $id === false || (new ConversationPolicy)->participate($this->user('store'), Conversation::query()->findOrFail($id));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer'],
            'message' => ['bail', 'required', 'string', 'max:65535', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen($value) > 65535) {
                    $fail(__('seller.common.the_message_may_not_exceed_65_535_bytes'));
                }
            }],
        ];
    }
}
