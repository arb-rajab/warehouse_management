<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base of the warehouse (WMS client) API's requests. The `wms.auth` middleware
 * is the gate, so there is nothing to authorize here, and a refusal is the
 * `{result, message, status}` envelope the client already parses rather than
 * Laravel's redirect or `errors` payload. Most of this API reports errors in
 * the body of an HTTP 200, so that stays the default; see
 * {@see self::$failureStatus}.
 */
abstract class WarehouseRequest extends FormRequest
{
    /**
     * The status in the envelope and on the response.
     */
    protected int $failureStatus = 200;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return string|list<string>
     */
    protected function failureMessage(Validator $validator): string|array
    {
        return $validator->errors()->all();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'result' => false,
            'message' => $this->failureMessage($validator),
            'status' => $this->failureStatus,
        ], $this->failureStatus));
    }
}
