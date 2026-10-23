<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Models\StoreUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStoreEmailRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected $errorBag = 'changeEmail';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:191', Rule::unique(StoreUser::class, 'email')->ignore($this->user('store')?->id)]];
    }
}
