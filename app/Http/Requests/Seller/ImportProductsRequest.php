<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Http\Requests\Concerns\ValidatesProductSpreadsheet;
use Illuminate\Foundation\Http\FormRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportProductsRequest extends FormRequest
{
    use RejectsUnexpectedInput;
    use ValidatesProductSpreadsheet;

    /** Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->productSpreadsheetRules();
        $rules['bulk_file'] = ['bail', ...$rules['bulk_file'], 'extensions:xlsx,xls,csv,txt', function (string $attribute, mixed $value, \Closure $fail): void {
            $type = match (strtolower($value->getClientOriginalExtension())) {
                'xlsx' => IOFactory::READER_XLSX,
                'xls' => IOFactory::READER_XLS,
                default => IOFactory::READER_CSV,
            };
            try {
                $readable = IOFactory::createReader($type)->canRead($value->getRealPath());
            } catch (\Throwable) {
                $readable = false;
            }
            if (! $readable) {
                $fail(__('validation.mimes', ['attribute' => __('validation.attributes.bulk_file'), 'values' => 'xlsx, xls, csv, txt']));
            }
        }];

        return $rules;
    }
}
