<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cell;
use Illuminate\Support\Collection;

/**
 * Shared QR-label building for the row-wide and single-cell QR-export PDFs —
 * used by both Admin\RowController and Admin\CellController so the printed
 * label text and QR payload stay in lockstep.
 */
trait BuildsCellQrLabels
{
    use BuildsQrLabels;

    /**
     * @param  Collection<int, Cell>  $cells
     * @return array<int, array{label: string, description: string, qrImage: string}>
     */
    private function cellQrLabels(string $rowLetter, Collection $cells): array
    {
        return $cells
            ->map(fn (Cell $cell) => [
                'label' => Cell::slotLabel($rowLetter, $cell->cell_number, $cell->flat_number),
                // Spelled out below the compact label — "A1·2" alone doesn't tell a
                // worker which digit is the cell and which is the flat once the
                // sticker is torn off the sheet and stuck on a shelf with no app
                // around it for context.
                'description' => $this->shapeArabicForPdf(__('messages.qr_label_description', [
                    'row' => $rowLetter,
                    'cell' => $cell->cell_number,
                    'flat' => $cell->flat_number,
                ])),
                // The mobile app's custom URL scheme, encoded directly — no web
                // redirect page in between. Only the app itself can open this link.
                'qrImage' => $this->qrImageDataUri("warehouseapp://cell?row={$rowLetter}&cell={$cell->cell_number}&flat={$cell->flat_number}"),
            ])
            ->all();
    }
}
