<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Exceptions\InvalidSlotStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyPalletRequest;
use App\Http\Requests\Api\V1\OpenPalletRequest;
use App\Http\Requests\Api\V1\StorePalletRequest;
use App\Http\Requests\Api\V1\TransferPalletRequest;
use App\Http\Resources\PalletResource;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PalletController extends Controller
{
    private const array EAGER_LOAD = ['product:id,name,image_url', 'cell.row:id,letter'];

    /**
     * Read a cell inside the surrounding transaction, holding a row lock on it so
     * concurrent requests cannot act on the same slot at the same time.
     */
    private function lockCell(int $cellId): Cell
    {
        return Cell::query()->lockForUpdate()->findOrFail($cellId);
    }

    /**
     * Write a CellStatusLog row for a cell state transition.
     */
    private function logCellStatus(
        Cell $cell,
        CellLogAction $action,
        CellState $fromState,
        CellState $toState,
        ?int $productId,
        ?int $palletId,
        int $userId,
        ?string $note,
        ?int $relatedCellId = null,
    ): void {
        CellStatusLog::create([
            'cell_id' => $cell->id,
            'related_cell_id' => $relatedCellId,
            'action' => $action,
            'from_state' => $fromState,
            'to_state' => $toState,
            'product_id' => $productId,
            'pallet_id' => $palletId,
            'user_id' => $userId,
            'note' => $note,
        ]);
    }

    #[DocumentedResponse(409, description: 'The requested slot is not empty (`error_code`: `slot_not_empty`).', type: 'array{message: string, error_code: string}')]
    public function store(StorePalletRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $pallet = DB::transaction(function () use ($request, $user) {
            $cell = $this->lockCell($request->resolvedSlot()->id);

            if ($cell->state !== CellState::Empty) {
                throw new InvalidSlotStateException('slot_not_empty', __('messages.slot_not_empty'));
            }

            $pallet = Pallet::create([
                'product_id' => $request->input('product_id'),
                'cell_id' => $cell->id,
                'expiration_date' => $request->input('expiration_date'),
            ]);

            $cell->update(['state' => CellState::Full]);

            $this->logCellStatus(
                $cell,
                CellLogAction::Stored,
                CellState::Empty,
                CellState::Full,
                $pallet->product_id,
                $pallet->id,
                $user->id,
                $request->input('note'),
            );

            return $pallet;
        });

        return (new PalletResource($pallet->load(self::EAGER_LOAD)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet->load(self::EAGER_LOAD));
    }

    #[DocumentedResponse(409, description: 'Only a full pallet can be opened (`error_code`: `pallet_not_full`).', type: 'array{message: string, error_code: string}')]
    public function open(OpenPalletRequest $request, Pallet $pallet): PalletResource
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $pallet, $user) {
            $cell = $this->lockCell($pallet->cell_id);

            if ($cell->state !== CellState::Full) {
                throw new InvalidSlotStateException('pallet_not_full', __('messages.pallet_not_full'));
            }

            $cell->update(['state' => CellState::Opened]);

            $this->logCellStatus(
                $cell,
                CellLogAction::Opened,
                CellState::Full,
                CellState::Opened,
                $pallet->product_id,
                $pallet->id,
                $user->id,
                $request->input('note'),
            );
        });

        return new PalletResource($pallet->load(self::EAGER_LOAD));
    }

    public function empty(EmptyPalletRequest $request, Pallet $pallet): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $pallet, $user) {
            $cell = $this->lockCell($pallet->cell_id);
            $fromState = $cell->state;
            $productId = $pallet->product_id;
            $palletId = $pallet->id;

            $pallet->delete();

            $cell->update(['state' => CellState::Empty]);

            $this->logCellStatus(
                $cell,
                CellLogAction::Emptied,
                $fromState,
                CellState::Empty,
                $productId,
                $palletId,
                $user->id,
                $request->input('note'),
            );
        });

        return response()->json(null, 204);
    }

    #[DocumentedResponse(409, description: 'The destination slot is not empty (`error_code`: `destination_not_empty`).', type: 'array{message: string, error_code: string}')]
    public function transfer(TransferPalletRequest $request, Pallet $pallet): PalletResource
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($pallet, $request, $user) {
            $sourceCell = $this->lockCell($pallet->cell_id);
            $destinationCell = $this->lockCell($request->resolvedSlot()->id);

            if ($destinationCell->state !== CellState::Empty) {
                throw new InvalidSlotStateException('destination_not_empty', __('messages.destination_not_empty'));
            }

            $carryOverState = $sourceCell->state;

            $sourceCell->update(['state' => CellState::Empty]);
            $destinationCell->update(['state' => $carryOverState]);
            $pallet->update(['cell_id' => $destinationCell->id]);

            $this->logCellStatus(
                $sourceCell,
                CellLogAction::TransferredOut,
                $carryOverState,
                CellState::Empty,
                $pallet->product_id,
                $pallet->id,
                $user->id,
                $request->input('note'),
                $destinationCell->id,
            );

            $this->logCellStatus(
                $destinationCell,
                CellLogAction::TransferredIn,
                CellState::Empty,
                $carryOverState,
                $pallet->product_id,
                $pallet->id,
                $user->id,
                $request->input('note'),
                $sourceCell->id,
            );
        });

        return new PalletResource($pallet->load(self::EAGER_LOAD));
    }
}
