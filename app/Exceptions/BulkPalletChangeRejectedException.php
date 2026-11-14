<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A bulk pallet correction (bulk note, product change) refused as a whole
 * because at least one selected pallet failed: nothing was written. `failed`
 * names each failing pallet with its own code, so the client can refresh and
 * unselect those and send the rest again:
 *
 * - `pallet_not_found`: the pallet is gone (removed, or emptied and deleted);
 * - `pallet_changed`: the `etag` sent no longer matches the pallet;
 * - `pallet_same_product`: the pallet already holds the target product;
 * - `cell_in_active_round`: its cell's row is being verified by an unfinished round.
 */
class BulkPalletChangeRejectedException extends Exception
{
    public readonly string $errorCode;

    /**
     * @param  list<array{id: int, error_code: string}>  $failed
     */
    public function __construct(public readonly array $failed)
    {
        $this->errorCode = 'bulk_rejected';

        parent::__construct(trans_choice('messages.bulk_rejected', count($failed), ['count' => count($failed)]));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'failed' => array_map(fn (array $failure): array => [
                ...$failure,
                'message' => __('messages.'.$failure['error_code']),
            ], $this->failed),
        ], 409);
    }
}
