<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSellerVerificationFormRequest extends FormRequest
{
    use ValidatesWebsiteSettings;

    public const TYPES = ['text', 'file', 'select', 'multi_select', 'radio'];

    public const SELECT_TYPES = ['select', 'multi_select', 'radio'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['present', 'array', 'list', 'max:100'],
            'fields.*' => ['required', 'array:type,label,options'],
            'fields.*.type' => ['required', 'string', Rule::in(self::TYPES)],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.options' => ['required_if:fields.*.type,select,multi_select,radio', 'prohibited_unless:fields.*.type,select,multi_select,radio', 'array', 'list', 'min:1', 'max:100'],
            'fields.*.options.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->refuseUnexpectedSettingInput($validator, ['fields']));
    }

    /** The seller screens decode options separately; use the legacy JSON flags. */
    public function storedForm(): string
    {
        /** @var list<array{type: 'text'|'file', label: string}|array{type: 'select'|'multi_select'|'radio', label: string, options: list<string>}> $fields */
        $fields = $this->validated('fields');
        $stored = [];
        foreach ($fields as $field) {
            $item = ['type' => $field['type'], 'label' => $field['label']];
            if (in_array($field['type'], self::SELECT_TYPES, true)) {
                $item['options'] = json_encode($field['options'], JSON_THROW_ON_ERROR);
            }
            $stored[] = $item;
        }

        return json_encode($stored, JSON_THROW_ON_ERROR);
    }
}
