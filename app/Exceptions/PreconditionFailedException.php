<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreconditionFailedException extends Exception
{
    public readonly string $errorCode;

    public function __construct()
    {
        $this->errorCode = 'precondition_failed';

        parent::__construct(__('messages.precondition_failed'));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], 412);
    }
}
