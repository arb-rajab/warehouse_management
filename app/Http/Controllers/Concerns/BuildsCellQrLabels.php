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
    private function cellQrLabels(string $rowLetter, Collection $cells, int $qrWidth, int $qrHeight, int $flatsCount): array
    {
        return $cells
            ->map(fn (Cell $cell) => [
                'label' => Cell::slotLabel($rowLetter, $cell->cell_number, $cell->flat_number),
                'labelImage' => 'data:image/svg+xml;base64,'.base64_encode(
                    $this->cellQrLabelImage($rowLetter, $cell, $qrWidth, $qrHeight, $flatsCount)
                ),
            ])
            ->all();
    }

    /**
     * One standalone SVG label (see qrLabelImage()) — QR plus the slot label
     * expanded to fill the space a description line would otherwise leave
     * blank — for downloading/printing just this cell, and (base64-encoded,
     * see cellQrLabels()) for embedding as-is into the row-wide PDF sheet.
     *
     * $flatsCount (the row's total level count) drives the level-arrow
     * triangle spliced into the label's corner — see withLevelArrow().
     */
    private function cellQrLabelImage(string $rowLetter, Cell $cell, int $qrWidth, int $qrHeight, int $flatsCount): string
    {
        $svg = $this->qrLabelImage(
            $this->cellDeepLink($rowLetter, $cell),
            Cell::slotLabel($rowLetter, $cell->cell_number, $cell->flat_number),
            null,
            app()->isLocale('ar') ? 'rtl' : 'ltr',
            $qrWidth,
            $qrHeight,
            expandPrimaryText: true,
        );

        return $this->withLevelArrow($svg, $qrWidth, $cell->flat_number, $flatsCount);
    }

    /**
     * Splices a small filled triangle into the label's top-right corner to
     * mark this cell's level: pointing up when it sits on its row's highest
     * flat_number, down when it sits on the lowest, and nothing for every
     * level in between. flat_number runs 1..$flatsCount (see
     * RowObserver::updated()), so 1 is always the lowest level and
     * $flatsCount the highest. A row with only one level ($flatsCount < 2)
     * is simultaneously its own highest and lowest cell — there's no
     * direction to indicate, so no triangle is drawn rather than picking one
     * arbitrarily.
     *
     * Drawn as a plain SVG polygon rather than a text glyph (e.g. "↑"/"↓")
     * because the row-wide PDF sheet's `font-family="sans-serif"` resolves,
     * for dompdf's PDF output, to the core Helvetica font — Latin-1 only.
     * The existing "·" slot-label separator is Latin-1 (U+00B7) and renders
     * fine there, but arrow glyphs (U+2191/U+2193) are outside Latin-1 and
     * would silently fail to render on the printed sheet. A polygon has no
     * font/encoding to worry about — it renders identically to the QR
     * itself, already a plain vector `<path>`.
     *
     * The triangle sits inside the label's top margin (y: 0..padding),
     * which qrLabelImage() always leaves blank above the QR regardless of
     * $width/$height or any QR-shrink adjustment (see qrLabelImage()'s
     * $qrX/$qrBottom docblock) — so it never overlaps the QR or the text
     * below it.
     */
    private function withLevelArrow(string $svg, int $width, int $flatNumber, int $flatsCount): string
    {
        $direction = match (true) {
            $flatsCount < 2 => null,
            $flatNumber === $flatsCount => 'up',
            $flatNumber === 1 => 'down',
            default => null,
        };

        if ($direction === null) {
            return $svg;
        }

        $size = self::LABEL_PADDING - 4;
        $right = $width - 4;
        $left = $right - $size;
        $top = 2;
        $bottom = $top + $size;
        $midX = (int) (($left + $right) / 2);

        $points = $direction === 'up'
            ? "{$midX},{$top} {$left},{$bottom} {$right},{$bottom}"
            : "{$left},{$top} {$right},{$top} {$midX},{$bottom}";

        return str_replace('</svg>', "<polygon points=\"{$points}\" fill=\"#111111\"/>\n</svg>", $svg);
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
