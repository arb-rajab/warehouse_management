<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesStoreUploadReference;
use App\Http\Requests\Concerns\ValidatesSupportReplies;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Create a Ticket" (shop.frontend.user.support_ticket.index), posting to
 * SupportTicketController::store. The subject is required and limited to
 * tickets.subject (varchar(255)); the details are required and held to the
 * back office's limit for support text (tickets.details is longText). The
 * photos are optional: the uploader's comma-separated image ids, the caller's
 * own. Markup is kept as sent, as before; the ticket pages print the details
 * through clean_html(). The errors go to their own bag, for the modal.
 */
class StoreSupportTicketRequest extends FormRequest
{
    use ValidatesStoreUploadReference;
    use ValidatesSupportReplies;

    /** @var string */
    protected $errorBag = 'storeTicket';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'details' => $this->supportReplyRules(),
            'attachments' => ['nullable', $this->retainableImageUploadIdsRule(null)],
        ];
    }
}
