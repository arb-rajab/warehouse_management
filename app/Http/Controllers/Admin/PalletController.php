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
     * target here is picked based on where the action came from, so each page gets
     * back its own current state instead of always landing on the map.
     *
     * The origin is read from the session's tracked previous URL (the same source
     * `back()` uses), not the `Referer` header — `config/secure-headers.php` sets
     * `Referrer-Policy: no-referrer` app-wide, so browsers never actually send that
     * header. The session value isn't affected by that policy: Laravel records it
     * server-side on every real (GET) page visit and leaves it untouched by the
     * action's own POST request, so it still reflects whichever page was open right
     * before the action fired. It's still untrusted input, so it only ever selects
     * between two known, hardcoded destinations — it's never used to build the
     * redirect URL. The `Referer` header is kept as a fallback purely so this stays
     * testable without a real prior page visit populating the session.
     */
    private function handle(Request $request, Cell $cell, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidSlotStateException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        if ($this->originatedFromRowShow($request)) {
            return redirect()->route('admin.rows.show', $cell->loadMissing('row:id,letter')->row->letter);
        }

        return redirect()->route('admin.cells.index', $request->query());
    }

    private function cellFor(Pallet $pallet): Cell
    {
        return $pallet->cell()->select(['id', 'row_id'])->firstOrFail();
    }

    private function originatedFromRowShow(Request $request): bool
    {
        $previousUrl = $request->session()->previousUrl() ?? $request->headers->get('referer');

        if ($previousUrl === null) {
            return false;
        }

        $path = parse_url($previousUrl, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/admin/rows/[^/]+$#', $path) === 1;
    }
}
