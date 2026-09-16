<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidSlotStateException;
use App\Http\Controllers\Concerns\RedirectsAfterCellAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmptyPalletRequest;
use App\Http\Requests\Admin\OpenPalletRequest;
use App\Http\Requests\Admin\RemovePalletBoxesRequest;
use App\Http\Requests\Admin\StorePalletRequest;
use App\Http\Requests\Admin\TransferPalletRequest;
use App\Http\Requests\Admin\UpdatePalletRequest;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\User;
use App\Services\PalletActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PalletController extends Controller
{
    use RedirectsAfterCellAction;

    public function __construct(private readonly PalletActionService $palletActions) {}

    public function store(StorePalletRequest $request, Cell $cell): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($request, $cell, function () use ($request, $cell, $user) {
            $this->palletActions->store(
                $cell->id,
                $request->integer('product_id'),
                $request->input('expiration_date'),
                $user->id,
                $request->input('note'),
            );
        });
    }

    public function open(OpenPalletRequest $request, Pallet $pallet): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($request, $this->cellFor($pallet), function () use ($request, $pallet, $user) {
            $this->palletActions->open(
                $pallet,
                $request->integer('boxes_count'),
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

        return $this->handle($request, $this->cellFor($pallet), function () use ($request, $pallet, $user) {
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

        return $this->handle($request, $this->cellFor($pallet), function () use ($request, $pallet, $user) {
            $this->palletActions->emptyPallet($pallet, $user->id, $request->input('note'));
        });
    }

    public function transfer(TransferPalletRequest $request, Pallet $pallet): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($request, $this->cellFor($pallet), function () use ($request, $pallet, $user) {
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

        return $this->handle($request, $this->cellFor($pallet), function () use ($request, $pallet, $user) {
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
     * Run a pallet-action closure, redirecting on success back to wherever the action
     * was triggered from, or surfacing an InvalidSlotStateException as a non-field
     * `action` error for ActionErrorBanner to render otherwise — the target state can
     * legitimately have changed since the page was rendered (another admin, or the
     * mobile app).
     *
     * The redirect target itself comes from `RedirectsAfterCellAction`, shared with
     * CellController::toggleActive() — see that trait for why it can't be inferred
     * from Referer/session state.
     */
    private function handle(Request $request, Cell $cell, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidSlotStateException $e) {
            return $this->redirectAfterCellAction($request, $cell)->withErrors(['action' => $e->getMessage()]);
        }

        return $this->redirectAfterCellAction($request, $cell);
    }

    private function cellFor(Pallet $pallet): Cell
    {
        return $pallet->cell()->select(['id', 'row_id'])->firstOrFail();
    }
}
