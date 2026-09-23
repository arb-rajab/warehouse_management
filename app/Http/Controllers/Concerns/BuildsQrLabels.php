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
     *
     * $size is the admin-configured QR size (Setting::current(), see
     * qrLabelImage()) — never a hardcoded value.
     */
    private function qrImageDataUri(string $data, int $size): string
    {
        // simplesoftwareio/simple-qrcode's generate() docblock omits the leading
        // backslash on its Illuminate\Support\HtmlString return type, so Larastan
        // resolves it relative to the vendor's own namespace into a nonexistent
        // class — override with the real type generate() actually returns here.
        /** @var HtmlString|string $svg */
        $svg = QrCode::format('svg')->size($size)->generate($data);

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
     * $width/$height (Setting::current()'s qr_code_width/qr_code_height) are
     * the label's total maximum box — the QR square plus any text below it,
     * not just the QR (see PR review discussion on the QR-size-setting
     * change). The QR itself is sized from $width alone and never shrinks to
     * make room for text, so when the configured $height leaves less room
     * than a wrapped line needs, the line list yields instead: lines beyond
     * whatever fits get dropped and the last visible one gets an ellipsis,
     * or the whole block is omitted if not even one line fits. Called
     * separately for the primary and secondary text blocks in qrLabelImage(),
     * each against however much vertical budget remains after the other.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function clampLinesToHeight(array $lines, int $startY, int $lineHeight, int $maxY): array
    {
        $visibleCount = 0;

        foreach ($lines as $index => $line) {
            if ($startY + $index * $lineHeight > $maxY) {
                break;
            }

            $visibleCount++;
        }

        if ($visibleCount === count($lines)) {
            return $lines;
        }

        if ($visibleCount === 0) {
            return [];
        }

        $visible = array_slice($lines, 0, $visibleCount);
        $visible[$visibleCount - 1] = rtrim($visible[$visibleCount - 1]).'…';

        // array_slice() alone is list-shaped, but PHPStan can't prove the
        // computed-index assignment above kept it that way — array_values()
        // re-establishes the list<string> guarantee explicitly.
        return array_values($visible);
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
     * Picks the largest primary-line font size (down to the default $minFontSize)
     * whose wrapped lines fit entirely within the vertical space below the QR,
     * without needing clampLinesToHeight() to truncate any of them. Cell slot
     * labels vary a lot in length (Row::MAX_DIMENSION allows 3-digit cell/flat
     * numbers), so a single fixed "large" size would silently drop the flat
     * number behind an ellipsis for a cell like "Z123·456" while looking fine
     * for "Z1·2" — trying sizes from the top down guarantees the full label is
     * always visible, just smaller when it needs to be.
     *
     * A bigger font also needs more clearance above its own baseline (glyphs
     * extend roughly 0.8x the font size above it), so each candidate's start
     * Y is computed from the font size itself rather than the fixed 24px gap
     * the default $minFontSize uses — otherwise a large font's text would
     * draw up into the QR instead of sitting below it.
     *
     * @return array{0: int, 1: int, 2: int} [fontSize, lineHeight, startY]
     */
    private function pickExpandedPrimaryFontSize(string $text, int $textMaxWidth, int $qrBottom, int $maxTextY, int $minFontSize, int $minLineHeight, int $minStartY): array
    {
        $topGap = 12;
        $bottomGap = 12;
        $maxFontSize = 64;

        for ($fontSize = $maxFontSize; $fontSize >= $minFontSize; $fontSize--) {
            $lineHeight = (int) round($fontSize * 1.2);
            $startY = $qrBottom + $topGap + (int) round($fontSize * 0.8);
            $lines = $this->wrapLabelText($text, $textMaxWidth, $fontSize);
            $bottom = $startY + (count($lines) - 1) * $lineHeight + $bottomGap;

            if ($bottom <= $maxTextY) {
                return [$fontSize, $lineHeight, $startY];
            }
        }

        return [$minFontSize, $minLineHeight, $minStartY];
    }

    /**
     * A single, self-contained SVG "image" label — one QR plus up to three
     * lines of plain legible text below it — for a single-item export
     * (one cell, one product) that a worker downloads and prints directly,
     * as opposed to the multi-label PDF sheet built by cellQrLabels() for
     * printing a whole row at once. $secondaryDirection controls the second
     * line's reading direction independently of the first, since a
     * product's Arabic name is always RTL regardless of the primary
     * (English) line next to it.
     *
     * $width/$height are Setting::current()'s qr_code_width/qr_code_height —
     * the label's total maximum box (QR plus any text below it), never a
     * hardcoded value. The QR itself is always a square sized from $width
     * alone ($qrSize = $width - padding*2) and never shrinks to make room
     * for text; text is what yields instead, via clampLinesToHeight(): a
     * name that wraps onto more lines than fit within $height gets truncated
     * with an ellipsis, or dropped entirely if not even one line fits. The
     * returned SVG's actual height still shrinks below $height when the
     * content is short (unchanged from before) — $height is a ceiling, not
     * a fixed canvas size — except in the one unavoidable case where $height
     * is configured smaller than the QR needs on its own (e.g. a much wider
     * than tall box): the QR is still never clipped, so the label grows past
     * $height rather than cut it off.
     *
     * $idText is an optional small caption line drawn directly below the QR,
     * above the primary line — currently only BuildsProductQrLabels passes
     * one (the product id), so BuildsCellQrLabels's call sites are unaffected
     * by leaving it null. It's plain digits/ASCII so it's always drawn LTR
     * regardless of locale, and it shares the same $height budget as the
     * other text: when it doesn't fit above $maxTextY it's dropped via
     * clampLinesToHeight() like any other line, and the primary line's start
     * position only shifts down when it actually got drawn.
     *
     * $expandPrimaryText grows the primary line's font size to fill whatever
     * vertical space is left below the QR (only BuildsCellQrLabels passes
     * true, since a cell label carries no secondary/explanation line to
     * occupy that space) — see pickExpandedPrimaryFontSize() for how the size
     * is chosen.
     */
    private function qrLabelImage(string $qrData, string $primaryText, ?string $secondaryText, string $secondaryDirection, int $width, int $height, ?string $idText = null, bool $expandPrimaryText = false): string
    {
        $padding = 20;
        $qrSize = $width - $padding * 2;
        $qrBottom = $padding + $qrSize;
        $centerX = (int) ($width / 2);
        $textMaxWidth = $width - $padding * 2;
        $maxTextY = $height - 16;
        $qrDataUri = $this->qrImageDataUri($qrData, $qrSize);

        $idMarkup = '';
        $idY = $qrBottom + 24;
        $idRendered = false;

        if ($idText !== null) {
            $idFontSize = 13;
            $idLineHeight = 16;
            $idLines = $this->clampLinesToHeight([$idText], $idY, $idLineHeight, $maxTextY);
            $idMarkup = $this->textLinesMarkup($idLines, $centerX, $idY, $idLineHeight, $idFontSize, '#555555', 'ltr');
            $idRendered = $idLines !== [];
        }

        $primaryFontSize = 18;
        $primaryLineHeight = 22;
        $primaryY = $idRendered ? $idY + 26 : $qrBottom + 24;

        if ($expandPrimaryText && ! $idRendered) {
            [$primaryFontSize, $primaryLineHeight, $primaryY] = $this->pickExpandedPrimaryFontSize(
                $primaryText,
                $textMaxWidth,
                $qrBottom,
                $maxTextY,
                $primaryFontSize,
                $primaryLineHeight,
                $primaryY,
            );
        }

        $primaryLines = $this->wrapLabelText($primaryText, $textMaxWidth, $primaryFontSize);
        $primaryLines = $this->clampLinesToHeight($primaryLines, $primaryY, $primaryLineHeight, $maxTextY);
        $primaryMarkup = $this->textLinesMarkup($primaryLines, $centerX, $primaryY, $primaryLineHeight, $primaryFontSize, '#111111', 'ltr', bold: true);

        // The Y just below whatever content actually got drawn — the last
        // visible primary line, or the id caption/QR bottom edge when no
        // primary line fit at all.
        $contentBottom = match (true) {
            $primaryLines !== [] => $primaryY + (count($primaryLines) - 1) * $primaryLineHeight,
            $idRendered => $idY,
            default => $qrBottom,
        };
        $labelHeight = $contentBottom + 16;

        $secondaryMarkup = '';

        if ($secondaryText !== null) {
            $secondaryFontSize = 16;
            $secondaryLineHeight = 20;
            $secondaryY = $contentBottom + ($primaryLines === [] ? 24 : 26);
            $secondaryLines = $this->wrapLabelText($secondaryText, $textMaxWidth, $secondaryFontSize);
            $secondaryLines = $this->clampLinesToHeight($secondaryLines, $secondaryY, $secondaryLineHeight, $maxTextY);
            $secondaryMarkup = $this->textLinesMarkup($secondaryLines, $centerX, $secondaryY, $secondaryLineHeight, $secondaryFontSize, '#555555', $secondaryDirection);

            if ($secondaryLines !== []) {
                $labelHeight = $secondaryY + (count($secondaryLines) - 1) * $secondaryLineHeight + 16;
            }
        }

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$labelHeight}" viewBox="0 0 {$width} {$labelHeight}">
                <rect width="100%" height="100%" fill="#ffffff"/>
                <image href="{$qrDataUri}" x="{$padding}" y="{$padding}" width="{$qrSize}" height="{$qrSize}"/>
                {$idMarkup}
                {$primaryMarkup}
                {$secondaryMarkup}
            </svg>
            SVG;
    }
}
