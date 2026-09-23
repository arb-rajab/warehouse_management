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
     * $qrWidth/$qrHeight come from Setting::current(), resolved once by the
     * caller (RowController::exportQrCodes) rather than re-queried per cell
     * here — a row can hold Row::MAX_DIMENSION² cells.
     *
     * The multi-label PDF sheet's grid (see resources/views/pdf/qr-labels.blade.php)
     * lays each label out at a fixed percentage of the page width, and its
     * `<img>` is CSS-scaled to fill that box (`width: 100%; height: auto`) —
     * so an admin-configured $qrWidth/$qrHeight never changes the sheet's
     * physical grid, only the encoded QR's resolution/sharpness. That's why
     * this call site is safe to wire up without a stricter bound than the
     * one UpdateSettingRequest/Setting already enforce.
     *
     * @param  Collection<int, Cell>  $cells
     * @return array<int, array{label: string, description: string, qrImage: string}>
     */
    private function cellQrLabels(string $rowLetter, Collection $cells, int $qrWidth, int $qrHeight): array
    {
        return $cells
            ->map(fn (Cell $cell) => [
                'label' => Cell::slotLabel($rowLetter, $cell->cell_number, $cell->flat_number),
                // Spelled out below the compact label — "A1·2" alone doesn't tell a
                // worker which digit is the cell and which is the flat once the
                // sticker is torn off the sheet and stuck on a shelf with no app
                // around it for context.
                'description' => $this->shapeArabicForPdf($this->cellQrLabelDescription($rowLetter, $cell)),
                // The mobile app's custom URL scheme, encoded directly — no web
                // redirect page in between. Only the app itself can open this link.
                'qrImage' => $this->qrImageDataUri($this->cellDeepLink($rowLetter, $cell), max($qrWidth, $qrHeight)),
            ])
            ->all();
    }

    /**
     * The single-cell counterpart to cellQrLabels() — one standalone SVG
     * image (see qrLabelImage()) for downloading/printing just this cell's
     * label, rather than a whole PDF sheet. Unlike the PDF sheet, it carries
     * no spelled-out description line below the label — the slot label is
     * the only text, drawn large enough (expandPrimaryText) to fill the
     * space the description would otherwise have left blank.
     */
    private function cellQrLabelImage(string $rowLetter, Cell $cell, int $qrWidth, int $qrHeight): string
    {
        return $this->qrLabelImage(
            $this->cellDeepLink($rowLetter, $cell),
            Cell::slotLabel($rowLetter, $cell->cell_number, $cell->flat_number),
            null,
            app()->isLocale('ar') ? 'rtl' : 'ltr',
            $qrWidth,
            $qrHeight,
            expandPrimaryText: true,
        );
    }

    /**
     * The PDF sheet's spelled-out description line (see cellQrLabels()).
     * cellQrLabelImage() no longer uses this — the single-cell SVG export
     * carries no description text at all.
     */
    private function cellQrLabelDescription(string $rowLetter, Cell $cell): string
    {
        return __('messages.qr_label_description', [
            'row' => $rowLetter,
            'cell' => $cell->cell_number,
            'flat' => $cell->flat_number,
        ]);
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
