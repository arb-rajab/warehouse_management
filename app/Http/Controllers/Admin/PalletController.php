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
     * target depends on which page the dialog was opened from. That can't be
     * inferred server-side here: the `Referer` header is stripped app-wide by
     * `config/secure-headers.php`'s `Referrer-Policy: no-referrer`, and the
     * session-tracked previous URL `back()` relies on doesn't help either, since
     * Inertia's client marks every visit (including full page-to-page navigation)
     * as an XHR request, which stops Laravel's session middleware from ever
     * updating it during normal SPA use — it stays frozen at whatever the last
     * true full browser page load was. So `PalletActionsDialog.vue` sends an
     * explicit `return_to` field (`'row'` or omitted) instead, validated against
     * a fixed `in:cells,row` list by `ValidatesReturnTo` — it only ever selects
     * between two known, hardcoded destinations, and the row it redirects to is
     * still derived from the actual cell/pallet the action operated on, never
     * from request input, so this can't be used to redirect somewhere unrelated.
     */
    private function handle(Request $request, Cell $cell, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidSlotStateException $e) {
            return $this->redirectFor($request, $cell)->withErrors(['action' => $e->getMessage()]);
        }

        return $this->redirectFor($request, $cell);
    }

    private function redirectFor(Request $request, Cell $cell): RedirectResponse
    {
        if ($request->input('return_to') === 'row') {
            return redirect()->route('admin.rows.show', $cell->loadMissing('row:id,letter')->row->letter);
        }

        return redirect()->route('admin.cells.index', $request->query());
    }

    private function cellFor(Pallet $pallet): Cell
    {
        return $pallet->cell()->select(['id', 'row_id'])->firstOrFail();
    }
}
