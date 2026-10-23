<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Http\Requests\Concerns\ValidatesSupportReplies;
use App\Models\ProductQuery;
use Illuminate\Foundation\Http\FormRequest;

class ReplyProductQueryRequest extends FormRequest
{
    use RejectsUnexpectedInput;
    use ValidatesSupportReplies;

    public function authorize(): bool
    {
        ProductQuery::query()->where('seller_id', $this->user('store')->id)->findOrFail($this->route('id'));

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return ['reply' => $this->supportReplyRules()];
    }
}
