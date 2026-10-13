<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Foundation\Http\FormRequest;

class FilterCustomersRequest extends FormRequest
{
    use FiltersPerPage;

    /** Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->perPageRules(), 'search' => ['nullable', 'string', 'max:255'], 'archived' => ['nullable', 'boolean'], 'important' => ['nullable', 'boolean'], 'verified' => ['nullable', 'boolean'], 'from_api' => ['nullable', 'boolean'], 'registration_completed' => ['nullable', 'boolean'], 'activity_status' => ['nullable', 'in:new_user,has_been_contacted'], 'kind' => ['nullable', 'in:products,categories']];
    }
}
