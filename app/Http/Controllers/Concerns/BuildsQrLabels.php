<?php

namespace App\Http\Controllers\Concerns;

use ArPHP\I18N\Arabic;
use Illuminate\Support\HtmlString;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Low-level PDF/QR helpers shared by every QR-label export — BuildsCellQrLabels
 * (row/cell labels) and BuildsProductQrLabels (product labels). Neither the SVG
 * QR encoding nor the Arabic PDF shaping is specific to what a label encodes.
 */
trait BuildsQrLabels
{
    /**
     * dompdf's text layout has no Arabic contextual shaping or bidi reordering
     * (see FIXME RTL markers throughout dompdf's FrameReflower/Style code) — it
     * only draws each character's isolated-form glyph in logical (storage)
     * order, which renders Arabic as disconnected letters read left-to-right.
     * `utf8Glyphs()` pre-shapes the string into joined presentation-form
     * glyphs already reordered into final left-to-right *display* order, so a
     * naive LTR-drawing engine like dompdf's still renders it correctly. The
     * font referenced in the qr-labels PDF view must carry glyphs for the
     * Arabic Presentation Forms-B block (U+FE70-FEFF) that produces.
     *
     * Only the dompdf-rendered multi-label sheet (RowController's row-wide
     * export, via cellQrLabels()) needs this — a standalone SVG label image
     * (qrLabelImage()) is drawn by a standards-compliant SVG renderer, which
     * already shapes and bidi-reorders Arabic text correctly on its own.
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

    /**
     * SVG `<text>` never wraps on its own, so a name wider than the label's
     * fixed canvas would draw past the edge and get visually clipped when
     * rasterized/printed. There's no text-measurement API available for a
     * plain `sans-serif` webfont at export time, so the max characters per
     * line is estimated from an average-character-width heuristic (each
     * character is assumed to fill roughly 55% of the font-size in pixels)
     * rather than measured exactly — safe because a slight over/under
     * estimate only shifts which word happens to land on which line, never
     * how many characters are shown. `wordwrap()`'s `cut` is enabled only as
     * a fallback for a single word wider than the whole line on its own.
     *
     * @return list<string>
     */
    private function wrapLabelText(string $text, int $maxWidth, int $fontSize): array
    {
        $avgCharWidth = $fontSize * 0.55;
        $maxChars = max(1, (int) floor($maxWidth / $avgCharWidth));

        return explode("\n", wordwrap($text, $maxChars, "\n", true));
    }

    /**
     * @param  list<string>  $lines
     */
    private function textLinesMarkup(array $lines, int $centerX, int $startY, int $lineHeight, int $fontSize, string $color, string $direction, bool $bold = false): string
    {
        $fontWeight = $bold ? ' font-weight="bold"' : '';
        $markup = '';

        foreach ($lines as $index => $line) {
            $y = $startY + $index * $lineHeight;
            $text = e($line);
            $markup .= <<<SVG
                <text x="{$centerX}" y="{$y}" direction="{$direction}" font-family="sans-serif" font-size="{$fontSize}"{$fontWeight} text-anchor="middle" fill="{$color}">{$text}</text>

                SVG;
        }

        return rtrim($markup);
    }

    /**
     * A single, self-contained SVG "image" label — one QR plus up to two
     * lines of plain legible text below it — for a single-item export
     * (one cell, one product) that a worker downloads and prints directly,
     * as opposed to the multi-label PDF sheet built by cellQrLabels() for
     * printing a whole row at once. $secondaryDirection controls the second
     * line's reading direction independently of the first, since a
     * product's Arabic name is always RTL regardless of the primary
     * (English) line next to it. Either line wraps onto additional lines
     * when it's too wide for the fixed-width canvas (see wrapLabelText());
     * $height grows to fit however many lines that produces, the same way
     * it already grows to fit an optional secondary line.
     */
    private function qrLabelImage(string $qrData, string $primaryText, ?string $secondaryText = null, string $secondaryDirection = 'ltr'): string
    {
        $qrSize = 240;
        $padding = 20;
        $width = $qrSize + $padding * 2;
        $centerX = (int) ($width / 2);
        $textMaxWidth = $width - $padding * 2;
        $primaryFontSize = 18;
        $primaryLineHeight = 22;
        $primaryY = $qrSize + $padding + 24;
        $qrDataUri = $this->qrImageDataUri($qrData);

        $primaryLines = $this->wrapLabelText($primaryText, $textMaxWidth, $primaryFontSize);
        $primaryMarkup = $this->textLinesMarkup($primaryLines, $centerX, $primaryY, $primaryLineHeight, $primaryFontSize, '#111111', 'ltr', bold: true);
        $lastPrimaryY = $primaryY + (count($primaryLines) - 1) * $primaryLineHeight;
        $height = $lastPrimaryY + 16;

        $secondaryMarkup = '';

        if ($secondaryText !== null) {
            $secondaryFontSize = 16;
            $secondaryLineHeight = 20;
            $secondaryY = $lastPrimaryY + 26;
            $secondaryLines = $this->wrapLabelText($secondaryText, $textMaxWidth, $secondaryFontSize);
            $secondaryMarkup = $this->textLinesMarkup($secondaryLines, $centerX, $secondaryY, $secondaryLineHeight, $secondaryFontSize, '#555555', $secondaryDirection);
            $lastSecondaryY = $secondaryY + (count($secondaryLines) - 1) * $secondaryLineHeight;
            $height = $lastSecondaryY + 16;
        }

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
                <rect width="100%" height="100%" fill="#ffffff"/>
                <image href="{$qrDataUri}" x="{$padding}" y="{$padding}" width="{$qrSize}" height="{$qrSize}"/>
                {$primaryMarkup}
                {$secondaryMarkup}
            </svg>
            SVG;
    }
}
