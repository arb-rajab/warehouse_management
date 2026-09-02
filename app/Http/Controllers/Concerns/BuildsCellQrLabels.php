<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cell;
use ArPHP\I18N\Arabic;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Shared QR-label building for the row-wide and single-cell QR-export PDFs —
 * used by both Admin\RowController and Admin\CellController so the printed
 * label text and QR payload stay in lockstep.
 */
trait BuildsCellQrLabels
{
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

    /**
     * dompdf's text layout has no Arabic contextual shaping or bidi reordering
     * (see FIXME RTL markers throughout dompdf's FrameReflower/Style code) — it
     * only draws each character's isolated-form glyph in logical (storage)
     * order, which renders Arabic as disconnected letters read left-to-right.
     * `utf8Glyphs()` pre-shapes the string into joined presentation-form
     * glyphs already reordered into final left-to-right *display* order, so a
     * naive LTR-drawing engine like dompdf's still renders it correctly. The
     * font referenced in cell-qr-labels.blade.php must carry glyphs for the
     * Arabic Presentation Forms-B block (U+FE70-FEFF) that produces.
     *
     * $hindo is false to keep Western digits, matching how the same
     * translation string renders un-shaped in the Vue/Inertia UI.
     */
    private function shapeArabicForPdf(string $text): string
    {
        if (! app()->isLocale('ar')) {
            return $text;
        }

        return (new Arabic)->utf8Glyphs($text, max_chars: 1000, hindo: false, forcertl: true);
    }

    /**
     * dompdf doesn't render inline `<svg>` markup (its SVG support only
     * covers rasterizing an SVG *source* referenced by an `<img>` tag), so
     * the QR is embedded as a base64 SVG data URI rather than inlined.
     *
     * SVG (not PNG) is used deliberately: bacon-qr-code's PNG backend renders
     * through Imagick, drawing each QR module as a separate composite call —
     * roughly 1.5-2.5s per code — while its SVG backend is plain string
     * building, ~10x faster. That difference is the gap between a row export
     * finishing in a few seconds and one timing out for any row of
     * non-trivial size.
     */
    private function qrImageDataUri(string $data): string
    {
        // simplesoftwareio/simple-qrcode's generate() docblock omits the leading
        // backslash on its Illuminate\Support\HtmlString return type, so Larastan
        // resolves it relative to the vendor's own namespace into a nonexistent
        // class — override with the real type generate() actually returns here.
        /** @var HtmlString|string $svg */
        $svg = QrCode::format('svg')->size(200)->generate($data);

        return 'data:image/svg+xml;base64,'.base64_encode((string) $svg);
    }
}
