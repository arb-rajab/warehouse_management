<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cell;
use Illuminate\Support\Collection;

/**
 * Shared QR-label building for the row-wide QR-export PDF sheet and the
 * single-cell QR-export image — used by both Admin\RowController and
 * Admin\CellController so the printed/exported label text and QR payload
 * stay in lockstep.
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
                'qrImage' => $this->qrImageDataUri($this->cellDeepLink($rowLetter, $cell)),
            ])
            ->all();
    }

    /**
     * The single-cell counterpart to cellQrLabels() — one standalone SVG
     * image (see qrLabelImage()) for downloading/printing just this cell's
     * label, rather than a whole PDF sheet.
     */
    private function cellQrLabelImage(string $rowLetter, Cell $cell): string
    {
        return $this->qrLabelImage(
            $this->cellDeepLink($rowLetter, $cell),
            Cell::slotLabel($rowLetter, $cell->cell_number, $cell->flat_number),
            __('messages.qr_label_description', [
                'row' => $rowLetter,
                'cell' => $cell->cell_number,
                'flat' => $cell->flat_number,
            ]),
            app()->isLocale('ar') ? 'rtl' : 'ltr',
        );
    }

    /**
     * The mobile app's custom URL scheme, encoded directly — no web redirect
     * page in between. Only the app itself can open this link.
     */
    private function cellDeepLink(string $rowLetter, Cell $cell): string
    {
        return "warehouseapp://cell?row={$rowLetter}&cell={$cell->cell_number}&flat={$cell->flat_number}";
    }
}
