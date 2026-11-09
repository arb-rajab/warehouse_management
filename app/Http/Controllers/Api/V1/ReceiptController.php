<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterReceiptsRequest;
use App\Http\Requests\Api\V1\StoreReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ReceiptService;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Truck receiving (WMS admins only): a draft receipt against a bill, checked
 * line by line and finalized into unplaced pallets. See .ai/rules/receiving.md.
 */
class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    /**
     * The receipts, newest first, 20 per page, with their line and file
     * counts. `warehouse_ids[]` keeps those received into these warehouses,
     * `status` the drafts or the finalized ones.
     */
    public function index(FilterReceiptsRequest $request): AnonymousResourceCollection
    {
        $receipts = Receipt::query()
            ->select(Receipt::SELECT_COLUMNS)
            ->with(Receipt::WITH_SUMMARY)
            ->withCount(['lines', 'files'])
            ->when($request->validated('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->forWarehouses($request->warehouseIds())
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return ReceiptResource::collection($receipts);
    }

    /**
     * Create a draft receipt for one warehouse with the bill's header and
     * lines (every line `pending`). Answers the receipt's detail.
     */
    public function store(StoreReceiptRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $receipt = $this->receipts->create(
            $user->id,
            $request->integer('warehouse_id'),
            (string) $request->string('bill_name'),
            $request->filled('bill_info') ? (string) $request->string('bill_info') : null,
            $request->billLines(),
        );

        return $this->detail($receipt)->response()->setStatusCode(201);
    }

    /**
     * A receipt with its bill files (each with its download `url`) and its
     * lines: product, what the bill expects, status, note and the pallets
     * that arrived (with the created `pallet_id` once finalized).
     */
    public function show(Receipt $receipt): ReceiptResource
    {
        return $this->detail($receipt);
    }

    /**
     * Finalize a draft: one unplaced pallet per pallet that arrived, owned by
     * the receipt's warehouse (`GET pallets/unplaced`, then
     * `POST pallets/{pallet}/place`). Mismatched lines are allowed. The
     * receipt can no longer change afterwards.
     */
    #[DocumentedResponse(409, description: 'The receipt is already finalized (`error_code`: `receipt_finalized`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(422, description: 'The receipt has no bill file, a line is still pending, or no pallet arrived at all (`error_code`: `receipt_has_no_files` / `receipt_has_pending_lines` / `receipt_has_no_pallets`).', type: 'array{message: string, error_code: string}')]
    public function finalize(Request $request, Receipt $receipt): ReceiptResource
    {
        /** @var User $user */
        $user = $request->user();

        return $this->detail($this->receipts->finalize($receipt, $user->id));
    }

    private function detail(Receipt $receipt): ReceiptResource
    {
        return new ReceiptResource($receipt->load(Receipt::withDetails())->loadCount(['lines', 'files']));
    }
}
