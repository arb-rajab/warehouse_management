<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Http\Requests\Concerns\ValidatesStoreUploadReference;
use App\Http\Requests\Concerns\ValidatesSupportReplies;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketReplyRequest extends FormRequest
{
    use RejectsUnexpectedInput;
    use ValidatesStoreUploadReference;
    use ValidatesSupportReplies;

    public function authorize(): bool
    {
        $id = filter_var($this->input('ticket_id'), FILTER_VALIDATE_INT);
        if ($id !== false) {
            Ticket::query()->where('user_id', $this->user('store')->id)->findOrFail($id);
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'integer'],
            'reply' => $this->supportReplyRules(),
            'attachments' => ['nullable', $this->retainableImageUploadIdsRule(null, ownOnly: true)],
        ];
    }
}
