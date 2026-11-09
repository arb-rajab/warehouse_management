<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckReceiptLineRequest;
use App\Http\Requests\Api\V1\StoreReceiptLineRequest;
use App\Http\Resources\ReceiptLineResource;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Services\ReceiptService;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Checking a draft receipt's lines against the pallets that arrived, and the
 * extra lines for pallets that were not on the bill (WMS admins only).
 */
class ReceiptLineController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    /**
     * Record the pallets of this line that arrived (replacing earlier ones;
     * `[]` when none did) and an optional note. A bill line becomes `matched`
     * when the count, every pallet's boxes and every expiry equal the bill (a
     * bill without an expiry is not compared), otherwise `mismatched`; an
     * extra line stays `extra`.
     */
    #[DocumentedResponse(409, description: 'The receipt is already finalized (`error_code`: `receipt_finalized`).', type: 'array{message: string, error_code: string}')]
    public function check(CheckReceiptLineRequest $request, Receipt $receipt, ReceiptLine $line): ReceiptLineResource
    {
        $line = $this->receipts->checkLine($receipt, $line, $request->receivedPallets(), $request->input('note'));

        return new ReceiptLineResource($line->load(ReceiptLine::withDetails()));
    }

    /**
     * Add an extra line: pallets of a product that arrived without being on
     * the bill (status `extra`, nothing expected).
     */
    #[DocumentedResponse(409, description: 'The receipt is already finalized (`error_code`: `receipt_finalized`).', type: 'array{message: string, error_code: string}')]
    public function store(StoreReceiptLineRequest $request, Receipt $receipt): JsonResponse
    {
        $line = $this->receipts->addExtraLine($receipt, $request->integer('product_id'), $request->receivedPallets(), $request->input('note'));

        return (new ReceiptLineResource($line->load(ReceiptLine::withDetails())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete an extra line of a draft receipt. A line of the bill cannot be
     * deleted: check it with no pallets when nothing of it arrived.
     */
    #[DocumentedResponse(409, description: 'The receipt is already finalized (`error_code`: `receipt_finalized`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(422, description: 'The line is on the bill, not an extra line (`error_code`: `receipt_line_not_extra`).', type: 'array{message: string, error_code: string}')]
    public function destroy(Receipt $receipt, ReceiptLine $line): Response
    {
        $this->receipts->deleteExtraLine($receipt, $line);

        return response()->noContent();
    }
}
