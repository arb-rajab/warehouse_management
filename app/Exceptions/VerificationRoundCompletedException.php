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
    public function __construct()
    {
        parent::__construct(__('messages.verification_round_completed'));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'verification_round_completed',
        ], 409);
    }
}
