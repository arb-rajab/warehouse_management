<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a mutating receiving call reaches a receipt that has already
 * been finalized: a finalized receipt is immutable, and corrections go
 * through the normal pallet actions (see ReceiptService).
 */
class ReceiptFinalizedException extends Exception
{
    public readonly string $errorCode;

    public function __construct()
    {
        $this->errorCode = 'receipt_finalized';

        parent::__construct(__('messages.receipt_finalized'));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], 409);
    }
}
