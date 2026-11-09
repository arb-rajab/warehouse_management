<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReceiptFileRequest;
use App\Http\Resources\ReceiptFileResource;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Services\ReceiptService;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The photos/PDFs of a receipt's bill (WMS admins only). They are stored on a
 * private disk and read only through show().
 */
class ReceiptFileController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    /**
     * Add a photo (jpg, jpeg, png, webp; re-encoded to JPEG) or PDF of the
     * bill to a draft receipt, at most 10 MB and 10 files per receipt.
     */
    #[DocumentedResponse(409, description: 'The receipt is already finalized (`error_code`: `receipt_finalized`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(422, description: 'The receipt already holds the most files allowed (`error_code`: `receipt_file_limit_reached`).', type: 'array{message: string, error_code: string}')]
    public function store(StoreReceiptFileRequest $request, Receipt $receipt): JsonResponse
    {
        /** @var UploadedFile $upload */
        $upload = $request->file('file');

        $file = $this->receipts->addFile($receipt, $upload);

        return (new ReceiptFileResource($file))->response()->setStatusCode(201);
    }

    /**
     * Read one bill file (inline, with its type and original name).
     */
    public function show(Receipt $receipt, ReceiptFile $file): StreamedResponse
    {
        return $file->inlineResponse();
    }

    /**
     * Remove one bill file of a draft receipt.
     */
    #[DocumentedResponse(409, description: 'The receipt is already finalized (`error_code`: `receipt_finalized`).', type: 'array{message: string, error_code: string}')]
    public function destroy(Receipt $receipt, ReceiptFile $file): Response
    {
        $this->receipts->deleteFile($receipt, $file);

        return response()->noContent();
    }
}
