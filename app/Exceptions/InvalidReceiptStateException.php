<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a draft receipt cannot take the requested change yet: finalizing
 * without a bill file, with a line still pending or with no pallet at all, a
 * file beyond the per-receipt limit, or deleting a line of the bill itself
 * (see ReceiptService).
 */
class InvalidReceiptStateException extends Exception
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], 422);
    }
}
