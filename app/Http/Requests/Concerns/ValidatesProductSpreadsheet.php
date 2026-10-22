<?php

namespace App\Http\Requests\Concerns;

trait ValidatesProductSpreadsheet
{
    /** @return array<string, array<mixed>> */
    protected function productSpreadsheetRules(): array
    {
        return ['bulk_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']];
    }
}
