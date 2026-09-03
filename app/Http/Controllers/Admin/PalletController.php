<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidSlotStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmptyPalletRequest;
use App\Http\Requests\Admin\OpenPalletRequest;
use App\Http\Requests\Admin\RemovePalletBoxesRequest;
use App\Http\Requests\Admin\StorePalletRequest;
use App\Http\Requests\Admin\TransferPalletRequest;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\User;
use App\Services\PalletActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PalletController extends Controller
{
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

    /**
     * Run a pallet-action closure, redirecting on success back to wherever the action
     * was triggered from, or surfacing an InvalidSlotStateException as a non-field
     * `action` error for ActionErrorBanner to render otherwise — the target state can
     * legitimately have changed since the page was rendered (another admin, or the
     * mobile app).
     *
     * Pallet actions are triggered from two different pages (the cell map and a
     * single row's page), so — unlike the single-origin "explicit route + $request
     * ->query()" quick-action pattern documented in controllers.md — the redirect
     * target here is picked from the `Referer` header rather than hardcoded, so each
     * page gets back its own current state instead of always landing on the map.
     * `Referer` (not the session-backed `back()` helper, which controllers.md rules
     * out for reliability reasons) is reliable here because it's read directly off
     * this request rather than depending on a prior GET request having stored it in
     * the session. It's still untrusted input, so it only ever selects between two
     * known, hardcoded destinations — it's never used to build the redirect URL.
     */
    private function handle(Request $request, Cell $cell, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidSlotStateException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        if ($this->refererIsRowShow($request)) {
            return redirect()->route('admin.rows.show', $cell->loadMissing('row:id,letter')->row->letter);
        }

        return redirect()->route('admin.cells.index', $request->query());
    }

    private function cellFor(Pallet $pallet): Cell
    {
        return $pallet->cell()->select(['id', 'row_id'])->firstOrFail();
    }

    private function refererIsRowShow(Request $request): bool
    {
        $referer = $request->headers->get('referer');

        if ($referer === null) {
            return false;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        return $path !== null && preg_match('#^/admin/rows/[^/]+$#', $path) === 1;
    }
}
