<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a round is started for rows that another unfinished round
 * already claims. The conflicting row letters are interpolated into the
 * message rather than added as an extra payload key, so the response shape
 * stays identical to every other domain exception here.
 */
class OverlappingVerificationRoundException extends Exception
{
    public readonly string $errorCode;

    /**
     * @param  array<int, string>  $rowLetters
     */
    public function __construct(array $rowLetters)
    {
        $this->errorCode = 'rows_already_in_active_round';

        parent::__construct(__('messages.rows_already_in_active_round', ['rows' => implode(', ', $rowLetters)]));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], 409);
    }
}
