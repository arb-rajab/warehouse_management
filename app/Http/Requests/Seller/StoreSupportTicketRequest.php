<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Http\Requests\StoreSupportTicketRequest as StorefrontSupportTicketRequest;

class StoreSupportTicketRequest extends StorefrontSupportTicketRequest
{
    use RejectsUnexpectedInput;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'attachments' => ['nullable', $this->retainableImageUploadIdsRule(null, ownOnly: true)]];
    }
}
