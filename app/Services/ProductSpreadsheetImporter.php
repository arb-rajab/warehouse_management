<?php

namespace App\Services;

use App\Models\ProductsImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Importer;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class ProductSpreadsheetImporter
{
    /** Keep row errors on the upload control for both store import forms. */
    public function import(ProductsImport $import, UploadedFile $file): void
    {
        try {
            app(Importer::class)->import($import, $file);
        } catch (ValidationException $exception) {
            if (! $exception instanceof ExcelValidationException) {
                throw $exception;
            }
            throw ValidationException::withMessages(['bulk_file' => collect($exception->failures())->flatMap(fn ($failure) => $failure->errors())->all()]);
        }
    }
}
