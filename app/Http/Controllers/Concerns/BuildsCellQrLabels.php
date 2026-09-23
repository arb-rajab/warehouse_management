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
     * The multi-label PDF sheet (see resources/views/pdf/qr-labels.blade.php)
     * gives every cell its own page, sized via `@page { size: $qrWidth
     * $qrHeight }` to exactly the admin-configured dimensions, and each page
     * embeds exactly the same label image cellQrLabelImage() produces for the
     * single-cell download — same QR, same expanded slot label, no separate
     * description line. Reusing that one label-rendering method rather than
     * re-deriving similar layout here keeps the row-wide PDF sheet and the
     * single-cell export permanently in lockstep: any future change to
     * qrLabelImage()'s padding/font-sizing/QR-shrink behavior applies to both
     * automatically, and the sheet can't silently drift from what a worker
     * gets from the single-cell button. That's also why this call site is
     * safe to wire up without a stricter bound than the one
     * UpdateSettingRequest/Setting already enforce.
     *
     * @param  Collection<int, Cell>  $cells
     * @return array<int, array{label: string, labelImage: string}>
     */
    private function cellQrLabels(string $rowLetter, Collection $cells, int $qrWidth, int $qrHeight): array
    {
        return $cells
            ->map(fn (Cell $cell) => [
                'label' => Cell::slotLabel($rowLetter, $cell->cell_number, $cell->flat_number),
                'labelImage' => 'data:image/svg+xml;base64,'.base64_encode(
                    $this->cellQrLabelImage($rowLetter, $cell, $qrWidth, $qrHeight)
                ),
            ])
            ->all();
    }

    /**
     * One standalone SVG label (see qrLabelImage()) — QR plus the slot label
     * expanded to fill the space a description line would otherwise leave
     * blank — for downloading/printing just this cell, and (base64-encoded,
     * see cellQrLabels()) for embedding as-is into the row-wide PDF sheet.
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
     * The mobile app's custom URL scheme, encoded directly — no web redirect
     * page in between. Only the app itself can open this link.
     */
    private function cellDeepLink(string $rowLetter, Cell $cell): string
    {
        return "warehouseapp://cell?row={$rowLetter}&cell={$cell->cell_number}&flat={$cell->flat_number}";
    }
}
