<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Any query about this product" (the chat modal of shop.frontend.product_details
 * and shop.frontend.digital_product_details), posting to
 * ConversationController::store. The product must be one the storefront shows
 * (published and approved): its seller receives the question. The title and
 * the message are required and limited to conversations.title (varchar(1000))
 * and messages.message (text). Markup is kept as sent, as before; every page
 * that prints them escapes it. The errors go to their own bag, so the product
 * page's other forms do not show them.
 */
class StoreConversationRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'storeConversation';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')->where('published', 1)->where('approved', 1)],
            'title' => ['required', 'string', 'max:1000'],
            'message' => ['required', 'string', 'max:65535'],
        ];
    }
}
