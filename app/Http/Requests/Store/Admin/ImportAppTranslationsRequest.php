<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use JsonException;

class ImportAppTranslationsRequest extends FormRequest
{
    /** @var array<string, string> */
    private array $translations = [];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['lang_file' => ['required', 'file', 'mimes:json,txt', 'max:5120']];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $file = $this->file('lang_file');
            if (! $file instanceof UploadedFile) {
                return;
            }
            try {
                $contents = $file->get();
                if ($contents === false) {
                    throw new JsonException;
                }
                $decoded = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
                if (! $decoded instanceof \stdClass || count(get_object_vars($decoded)) > 5000) {
                    throw new JsonException;
                }
                foreach (get_object_vars($decoded) as $key => $value) {
                    if (! is_string($key) || $key === '' || mb_strlen($key) > 255 || ! is_string($value) || mb_strlen($value) > 255) {
                        throw new JsonException;
                    }
                    $this->translations[$key] = $value;
                }
            } catch (JsonException) {
                $validator->errors()->add('lang_file', __('validation.json', ['attribute' => 'lang_file']));
            }
        }];
    }

    /** @return array<string, string> */
    public function translations(): array
    {
        return $this->translations;
    }
}
