<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cell;
use App\Models\Row;
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
     * The multi-label PDF sheet gives every cell its own page, cropped to
     * this image's actual canvas rather than the configured maximum height.
     * It embeds the same SVG as the single-cell download, keeping the QR,
     * bold slot text, and arrow below the text identical in both exports.
     *
     * $row needs `warehouse_id`, `letter` and `flats_count`.
     *
     * @param  Collection<int, Cell>  $cells
     * @return array<int, array{label: string, labelImage: string}>
     */
    private function cellQrLabels(Row $row, Collection $cells, int $qrWidth, int $qrHeight): array
    {
        return $cells
            ->map(fn (Cell $cell) => [
                'label' => Cell::slotLabel($row->letter, $cell->cell_number, $cell->flat_number),
                'labelImage' => 'data:image/svg+xml;base64,'.base64_encode(
                    $this->cellQrLabelImage($row, $cell, $qrWidth, $qrHeight)
                ),
            ])
            ->all();
    }

    /**
     * One standalone SVG label (see qrLabelImage()) — QR beside a large bold
     * slot label with a level arrow below the text — for downloading/
     * printing just this cell, and (base64-encoded, see cellQrLabels()) for
     * embedding as-is into the row-wide PDF sheet.
     *
     * The row's `flats_count` (its total level count) drives the arrow
     * direction — see cellLevelArrowDirection().
     */
    private function cellQrLabelImage(Row $row, Cell $cell, int $qrWidth, int $qrHeight): string
    {
        return $this->qrLabelImage(
            $this->cellDeepLink($row, $cell),
            Cell::slotLabel($row->letter, $cell->cell_number, $cell->flat_number),
            null,
            app()->isLocale('ar') ? 'rtl' : 'ltr',
            $qrWidth,
            $qrHeight,
            expandPrimaryText: true,
            primaryArrow: $this->cellLevelArrowDirection($cell->flat_number, $row->flats_count),
        );
    }

    /**
     * 'up' when this cell sits on its row's highest flat_number, 'down' when
     * it sits on the lowest, and 'dash' for every level in between.
     * flat_number runs 1..$flatsCount (see RowObserver::updated()), so 1 is
     * always the lowest level and $flatsCount the highest. A row with only
     * one level ($flatsCount < 2) is simultaneously its own highest and
     * lowest cell — there's no up/down direction to indicate, so it falls
     * back to 'dash' the same way an actual mid-level cell does.
     */
    private function cellLevelArrowDirection(int $flatNumber, int $flatsCount): string
    {
        return match (true) {
            $flatsCount < 2 => 'dash',
            $flatNumber === $flatsCount => 'up',
            $flatNumber === 1 => 'down',
            default => 'dash',
        };
    }

    /**
     * The mobile app's custom URL scheme, encoded directly — no web redirect
     * page in between. Only the app itself can open this link. `warehouse`
     * (the row's warehouse id) was appended when row letters became unique
     * per warehouse: it is what the app sends as `warehouse_id` to pick the
     * row; labels printed before it still carry row/cell/flat only.
     */
    private function cellDeepLink(Row $row, Cell $cell): string
    {
        return "warehouseapp://cell?row={$row->letter}&cell={$cell->cell_number}&flat={$cell->flat_number}&warehouse={$row->warehouse_id}";
    }
}
