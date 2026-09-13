<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a report is submitted for a cell in a row the round does not
 * cover. A round only freezes the rows it claims (see
 * PalletActionService::lockCell()), so a report from outside them would carry
 * no consistency guarantee at all — the pallet could be moving as it is read.
 */
class CellOutsideRoundRowsException extends Exception
{
    public readonly string $errorCode;

    public function __construct()
    {
        $this->errorCode = 'cell_outside_round_rows';

        parent::__construct(__('messages.cell_outside_round_rows'));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], 409);
    }
}
