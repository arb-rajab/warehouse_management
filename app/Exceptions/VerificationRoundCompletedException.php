<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a report is submitted against a CellVerificationRound that has
 * already been marked completed.
 */
class VerificationRoundCompletedException extends Exception
{
    public readonly string $errorCode;

    public function __construct()
    {
        $this->errorCode = 'verification_round_completed';

        parent::__construct(__('messages.verification_round_completed'));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], 409);
    }
}
