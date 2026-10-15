<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\HtmlString;
use LogicException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Low-level SVG/PDF label helpers — QR encoding plus text wrapping/clamping —
 * used by BuildsCellQrLabels (row/cell labels) and, for its text layout only,
 * BuildsProductQrLabels (product barcode labels).
 */
trait BuildsQrLabels
{
    /**
     * The blank margin around the label's content on every side.
     */
    private const int LABEL_PADDING = 20;

    /**
     * The QR's own SVG markup, minus its outer `<svg>` wrapper, for splicing
     * straight into qrLabelImage()'s label SVG. It can't be referenced from
     * there as a nested `<image href="data:image/svg+xml...">` instead:
     * browsers render that fine, but dompdf (the row-wide PDF sheet, see
     * BuildsCellQrLabels::cellQrLabels()) draws an `<image>` found *inside*
     * an SVG through php-svg-lib's SurfaceCpdf::image(), which only handles
     * raster formats and silently draws nothing for an SVG source — the PDF
     * got every label's text but no QR at all. Inlined, the QR is parsed as
     * part of the same top-level SVG document, which both render.
     *
     * The wrapper is stripped rather than kept as a nested `<svg x y>`
     * viewport because php-svg-lib treats a nested `<svg>` as a plain group,
     * ignoring its x/y/viewBox — the caller positions this markup with a
     * `<g transform>` instead. No scaling is needed on top of that: the QR
     * is generated at exactly $size, so its own coordinates are already px.
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
    private function qrSvgInnerMarkup(string $data, int $size): string
    {
        // simplesoftwareio/simple-qrcode's generate() docblock omits the leading
        // backslash on its Illuminate\Support\HtmlString return type, so Larastan
        // resolves it relative to the vendor's own namespace into a nonexistent
        // class — override with the real type generate() actually returns here.
        /** @var HtmlString|string $svg */
        $svg = QrCode::format('svg')->size($size)->generate($data);

        if (preg_match('/<svg\b[^>]*>(.*)<\/svg>/s', (string) $svg, $matches) !== 1) {
            throw new LogicException('QR code generator returned no <svg> element.');
        }

        return $matches[1];
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
     * Caps $lines at $maxLines, ending the last kept line with an ellipsis
     * when anything was cut.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function limitLabelLines(array $lines, int $maxLines): array
    {
        if (count($lines) <= $maxLines) {
            return $lines;
        }

        $kept = array_slice($lines, 0, $maxLines);
        $kept[$maxLines - 1] = rtrim($kept[$maxLines - 1]).'…';

        return array_values($kept);
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
     * A filled arrow-shaped polygon (shaft + triangular head) pointing 'up',
     * 'down', or 'horizontal' (pointing right), sized to exactly fill the
     * given box. Used to draw a same-height, easily-readable level indicator
     * beside a label's primary text (see qrLabelImage()'s $primaryArrow) —
     * a plain vector shape rather than a text glyph (e.g. "↑"/"↓") because
     * the row-wide PDF sheet's `font-family="sans-serif"` resolves, for
     * dompdf's PDF output, to the core Helvetica font — Latin-1 only. The
     * existing "·" slot-label separator is Latin-1 (U+00B7) and renders fine
     * there, but arrow glyphs are outside Latin-1 and would silently fail to
     * render on the printed sheet. A polygon has no font/encoding to worry
     * about — it renders identically to the QR itself, already a plain
     * vector `<path>`.
     */
    private function arrowMarkup(string $direction, int $x0, int $y0, int $x1, int $y1): string
    {
        if ($x1 - $x0 < 4 || $y1 - $y0 < 4) {
            return '';
        }

        $points = match ($direction) {
            'up' => $this->verticalArrowPoints($x0, $y0, $x1, $y1, pointingUp: true),
            'down' => $this->verticalArrowPoints($x0, $y0, $x1, $y1, pointingUp: false),
            default => $this->horizontalArrowPoints($x0, $y0, $x1, $y1),
        };

        return '<polygon points="'.$points.'" fill="#111111"/>';
    }

    private function verticalArrowPoints(int $x0, int $y0, int $x1, int $y1, bool $pointingUp): string
    {
        $cx = intdiv($x0 + $x1, 2);
        $headDepth = (int) round(($y1 - $y0) * 0.5);
        $shaftHalfWidth = (int) round(($x1 - $x0) * 0.2);

        [$tipY, $baseY, $farY] = $pointingUp
            ? [$y0, $y0 + $headDepth, $y1]
            : [$y1, $y1 - $headDepth, $y0];

        return $this->polygonPoints([
            [$cx, $tipY],
            [$x0, $baseY],
            [$cx - $shaftHalfWidth, $baseY],
            [$cx - $shaftHalfWidth, $farY],
            [$cx + $shaftHalfWidth, $farY],
            [$cx + $shaftHalfWidth, $baseY],
            [$x1, $baseY],
        ]);
    }

    private function horizontalArrowPoints(int $x0, int $y0, int $x1, int $y1): string
    {
        $cy = intdiv($y0 + $y1, 2);
        $headDepth = (int) round(($x1 - $x0) * 0.5);
        $shaftHalfHeight = (int) round(($y1 - $y0) * 0.2);
        $baseX = $x1 - $headDepth;

        return $this->polygonPoints([
            [$x1, $cy],
            [$baseX, $y0],
            [$baseX, $cy - $shaftHalfHeight],
            [$x0, $cy - $shaftHalfHeight],
            [$x0, $cy + $shaftHalfHeight],
            [$baseX, $cy + $shaftHalfHeight],
            [$baseX, $y1],
        ]);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $points
     */
    private function polygonPoints(array $points): string
    {
        return implode(' ', array_map(fn (array $point) => "{$point[0]},{$point[1]}", $points));
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
     * hardcoded value. The QR is a square sized from $width alone ($qrSize =
     * $width - padding*2), shrunk slightly below that only when $height
     * leaves less than $minPrimaryZone of room below it (e.g. $width and
     * $height configured equal) — see the guard right after $qrSize/$qrBottom
     * below. Beyond that guaranteed minimum, text is still what yields via
     * clampLinesToHeight(): a name that wraps onto more lines than fit within
     * $height gets truncated with an ellipsis, or dropped entirely if not
     * even one line fits. The returned SVG's actual height still shrinks
     * below $height when the content is short (unchanged from before) —
     * $height is a ceiling, not a fixed canvas size — except in the one
     * unavoidable case where $height is configured smaller than the
     * (possibly already-shrunk) QR needs on its own: the QR is still never
     * clipped, so the label grows past $height rather than cut it off.
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
     *
     * $primaryArrow ('up'/'down'/'horizontal', see arrowMarkup()) reserves a
     * column to the right of the primary text — only BuildsCellQrLabels
     * passes one, to mark a cell's level. The reservation comes out of the
     * *same* $textMaxWidth the primary text itself wraps within (rather than
     * measuring the rendered text and placing the arrow just past it — no
     * such text-measurement API is available, see wrapLabelText()), so a
     * long label auto-wraps onto more/narrower lines to make room instead of
     * the arrow ever overlapping or overflowing it. The arrow's box then
     * spans the full height of however many lines that produced, so it reads
     * as large as the label itself rather than a small fixed-size icon.
     */
    private function qrLabelImage(string $qrData, string $primaryText, ?string $secondaryText, string $secondaryDirection, int $width, int $height, ?string $idText = null, bool $expandPrimaryText = false, ?string $primaryArrow = null): string
    {
        $padding = self::LABEL_PADDING;
        $qrSize = $width - $padding * 2;
        $qrBottom = $padding + $qrSize;
        $qrX = $padding;
        $centerX = (int) ($width / 2);
        $textMaxWidth = $width - $padding * 2;
        $maxTextY = $height - 16;

        // The primary line's default (unexpanded) layout needs a 24px gap
        // below the QR plus its own 22px line height — 46px total. Without
        // this guard, a $width/$height configured equal (or otherwise close)
        // leaves $qrBottom past $maxTextY, and clampLinesToHeight() drops the
        // primary line entirely rather than truncating it, since it never
        // gets so much as its first line's worth of room. Shrinking the QR
        // itself is a last resort — only enough to guarantee that minimum,
        // and never below 60% of $width, so the QR stays comfortably
        // scannable at any configured size.
        $minPrimaryZone = 24 + 22;
        $tightestAllowedQrBottom = $maxTextY - $minPrimaryZone;

        if ($qrBottom > $tightestAllowedQrBottom) {
            $deficit = $qrBottom - $tightestAllowedQrBottom;
            $shrinkBy = max(0, min($deficit, $qrSize - (int) ($width * 0.6)));
            $qrSize -= $shrinkBy;
            $qrBottom = $padding + $qrSize;
            // Re-center horizontally — $qrX started at $padding on the
            // assumption that $qrSize filled the full $width - padding*2
            // span; a shrunk QR no longer does, so it must move inward to
            // stay centered rather than hug the left/start edge.
            $qrX = (int) (($width - $qrSize) / 2);
        }

        $qrMarkup = $this->qrSvgInnerMarkup($qrData, $qrSize);

        // Reserving a fixed fraction of $textMaxWidth (rather than a size
        // derived from the primary font, which isn't picked until after this
        // point when $expandPrimaryText is true) keeps the arrow column's
        // width independent of the font-size search below — the search just
        // sees a narrower $primaryTextMaxWidth to wrap/fit within, the same
        // way it already reacts to any other width.
        $arrowGap = 12;
        $arrowWidth = $primaryArrow !== null ? (int) round($textMaxWidth * 0.3) : 0;
        $primaryTextMaxWidth = $primaryArrow !== null ? max(1, $textMaxWidth - $arrowWidth - $arrowGap) : $textMaxWidth;
        $primaryCenterX = $primaryArrow !== null ? (int) ($padding + $primaryTextMaxWidth / 2) : $centerX;

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
                $primaryTextMaxWidth,
                $qrBottom,
                $maxTextY,
                $primaryFontSize,
                $primaryLineHeight,
                $primaryY,
            );
        }

        $primaryLines = $this->wrapLabelText($primaryText, $primaryTextMaxWidth, $primaryFontSize);
        $primaryLines = $this->clampLinesToHeight($primaryLines, $primaryY, $primaryLineHeight, $maxTextY);
        $primaryMarkup = $this->textLinesMarkup($primaryLines, $primaryCenterX, $primaryY, $primaryLineHeight, $primaryFontSize, '#111111', 'ltr', bold: true);

        $arrowMarkup = '';

        if ($primaryArrow !== null && $primaryLines !== []) {
            $arrowTop = $primaryY - (int) round($primaryFontSize * 0.8);
            $arrowBottom = $primaryY + (count($primaryLines) - 1) * $primaryLineHeight + (int) round($primaryFontSize * 0.2);
            $arrowLeft = $padding + $primaryTextMaxWidth + $arrowGap;
            $arrowRight = $width - $padding;
            $arrowMarkup = $this->arrowMarkup($primaryArrow, $arrowLeft, $arrowTop, $arrowRight, $arrowBottom);
        }

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
                <g transform="translate({$qrX},{$padding})">{$qrMarkup}</g>
                {$idMarkup}
                {$primaryMarkup}
                {$arrowMarkup}
                {$secondaryMarkup}
            </svg>
            SVG;
    }
}
