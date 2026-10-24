<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidSlotStateException;
use App\Http\Controllers\Concerns\RedirectsAfterCellAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmptyPalletRequest;
use App\Http\Requests\Admin\FilterMatchingPalletsRequest;
use App\Http\Requests\Admin\OpenPalletRequest;
use App\Http\Requests\Admin\RemovePalletBoxesRequest;
use App\Http\Requests\Admin\StorePalletRequest;
use App\Http\Requests\Admin\TransferPalletRequest;
use App\Http\Requests\Admin\UpdatePalletRequest;
use App\Http\Resources\MatchingPalletResource;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use App\Services\PalletActionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PalletController extends Controller
{
    use RedirectsAfterCellAction;

    public function __construct(private readonly PalletActionService $palletActions) {}

    /**
     * The "matching pallets" list a dashboard stat tile or a product's
     * occupancy count links to (see FilterMatchingPalletsRequest), ordered by
     * expiration date then add date so the most urgent pallets lead. The page
     * itself offers a button that opens the warehouse map on the filtered
     * products.
     */
    public function index(FilterMatchingPalletsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        $pallets = Pallet::query()
            ->listedForAdmin()
            ->matchingHighlightFilters($request)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Pallets/Index', [
            'pallets' => $this->paginated(MatchingPalletResource::collection($pallets)),
            'filters' => [
                ...$request->only(['state', 'is_active', 'expired', 'expires_within_days', 'stale_after_days', 'product_id']),
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'products' => Product::selectedOptions($request->productIds() ?? []),
            ],
        ]);
    }

    public function store(StorePalletRequest $request, Cell $cell): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($cell, function () use ($request, $cell, $user) {
            $this->palletActions->store(
                $cell->id,
                $request->integer('product_id'),
                $request->input('expiration_date'),
                $user->id,
                $request->input('note'),
                $request->imageReviewData(),
            );
        });
    }

    public function open(OpenPalletRequest $request, Pallet $pallet): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($this->cellFor($pallet), function () use ($request, $pallet, $user) {
            $this->palletActions->open(
                $pallet,
                $request->filled('boxes_count') ? $request->integer('boxes_count') : null,
                $request->boolean('confirm_empty'),
                $user->id,
                $request->input('note'),
            );
        });
    }

    public function removeBoxes(RemovePalletBoxesRequest $request, Pallet $pallet): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($this->cellFor($pallet), function () use ($request, $pallet, $user) {
            $this->palletActions->removeBoxes(
                $pallet,
                $request->integer('boxes_count'),
                $request->boolean('confirm_empty'),
                $user->id,
                $request->input('note'),
            );
        });
    }

    public function empty(EmptyPalletRequest $request, Pallet $pallet): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($this->cellFor($pallet), function () use ($request, $pallet, $user) {
            $this->palletActions->emptyPallet($pallet, $user->id, $request->input('note'));
        });
    }

    public function transfer(TransferPalletRequest $request, Pallet $pallet): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($this->cellFor($pallet), function () use ($request, $pallet, $user) {
            $this->palletActions->transfer(
                $pallet,
                $request->resolvedSlot()->id,
                $user->id,
                $request->input('note'),
            );
        });
    }

    public function update(UpdatePalletRequest $request, Pallet $pallet): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($this->cellFor($pallet), function () use ($request, $pallet, $user) {
            $this->palletActions->update(
                $pallet,
                $request->integer('product_id'),
                $request->input('expiration_date'),
                $request->integer('remaining_boxes'),
                $user->id,
                $request->boolean('confirm_empty'),
            );
        });
    }

    /**
     * Run a pallet-action closure, redirecting back to the cell's row page (via
     * `RedirectsAfterCellAction`, shared with CellController::toggleActive()), and
     * surfacing an InvalidSlotStateException as a non-field `action` error for
     * ActionErrorBanner to render — the target state can legitimately have changed
     * since the page was rendered (another admin, or the mobile app).
     */
    private function handle(Cell $cell, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidSlotStateException $e) {
            return $this->redirectAfterCellAction($cell)->withErrors(['action' => $e->getMessage()]);
        }

        return $this->redirectAfterCellAction($cell);
    }

    private function cellFor(Pallet $pallet): Cell
    {
        return $pallet->cell()->select(['id', 'row_id'])->firstOrFail();
    }
}
