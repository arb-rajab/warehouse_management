<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Admin\StoreProductBarcodeRequest as AdminStoreProductBarcodeRequest;
use App\Models\Product;
use App\Services\ProductBarcodeAttacher;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * The mobile twin of the WMS add-barcode request: the same trimming and the
 * same rules, `NewProductBarcode` included (the controller repeats them under
 * its lock). The one difference is the failure shape: a value another product
 * holds answers 409 `barcode_taken` with those products' ids, like
 * `ambiguous_barcode`; every other failure, a value this product already
 * holds included, is a 422.
 */
class StoreProductBarcodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('barcode'))) {
            $this->merge(['barcode' => trim($this->input('barcode'))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return AdminStoreProductBarcodeRequest::barcodeRules();
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        $barcode = $this->input('barcode');

        if (is_string($barcode)
            && Validator::make(['barcode' => $barcode], AdminStoreProductBarcodeRequest::barcodeFormatRules())->passes()
            && ($holders = app(ProductBarcodeAttacher::class)->otherHolders($this->product(), $barcode)) !== []) {
            throw new HttpResponseException(self::takenResponse($holders));
        }

        parent::failedValidation($validator);
    }

    /**
     * @param  list<int>  $productIds
     */
    public static function takenResponse(array $productIds): JsonResponse
    {
        return response()->json([
            'message' => __('messages.barcode_taken'),
            'error_code' => 'barcode_taken',
            'product_ids' => $productIds,
        ], Response::HTTP_CONFLICT);
    }

    private function product(): Product
    {
        /** @var Product */
        return $this->route('product');
    }
}
