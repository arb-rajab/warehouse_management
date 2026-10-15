<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use InvalidArgumentException;

/**
 * Shared label building for the single-product barcode export image
 * (Admin\ProductController) — a Code 128 subset B barcode plus the product's
 * id and its name in the app's active locale only.
 *
 * One barcode encodes one product id (the bare id, as ASCII digits), not one
 * physical pallet/box — every unit of that product carries an identical
 * printed label. Only the id is encoded: name/ar_name/image_url can change
 * after a label is printed, so the mobile app resolves current data live via
 * GET /api/v1/products/{id}. The printed name is purely for a human to verify
 * the sticker against the box before scanning.
 *
 * The id is encoded bare rather than as the `warehouseapp://product?id=`
 * deep link the old QR carried: Code 128 is a 1D symbology whose width grows
 * with every character, and the full URI would shrink each bar below what a
 * scanner can resolve at the label's configured width.
 */
trait BuildsProductQrLabels
{
    use BuildsQrLabels;

    private const int CODE128_START_B = 104;

    private const int CODE128_STOP = 106;

    private const int BARCODE_QUIET_ZONE_MODULES = 10;

    /** Narrowest a bar module may print, in label pixels, before the label widens. */
    private const int BARCODE_MIN_MODULE_PX = 2;

    /**
     * Bar/space widths (in modules) of every Code 128 symbol, indexed by
     * symbol value. The last entry (106) is the 13-module stop pattern.
     *
     * @var list<string>
     */
    private const array CODE128_PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    /**
     * Largest font sizes the label tries, top down, for its two text blocks.
     */
    private const int PRODUCT_LABEL_ID_FONT_SIZE = 34;

    private const int PRODUCT_LABEL_NAME_FONT_SIZE = 28;

    /** The name never takes more lines than this, however tall the label may be. */
    private const int PRODUCT_LABEL_NAME_MAX_LINES = 2;

    /**
     * The bar/space module widths of $data as a Code 128 subset B symbol:
     * start B, one symbol per character, the mod-103 checksum, then stop.
     * Subset B covers printable ASCII (32-126), which is all a product id needs.
     *
     * @return list<int> alternating bar, space, bar... widths in modules
     */
    private function code128BModules(string $data): array
    {
        $symbols = [self::CODE128_START_B];
        $checksum = self::CODE128_START_B;

        foreach (str_split($data) as $position => $character) {
            $code = ord($character);

            if ($code < 32 || $code > 126) {
                throw new InvalidArgumentException('Code 128 subset B encodes printable ASCII only.');
            }

            $symbols[] = $code - 32;
            $checksum += ($position + 1) * ($code - 32);
        }

        $symbols[] = $checksum % 103;
        $symbols[] = self::CODE128_STOP;

        return array_map('intval', str_split(implode('', array_map(
            fn (int $symbol): string => self::CODE128_PATTERNS[$symbol],
            $symbols,
        ))));
    }

    /**
     * The barcode's bars as a single path, scaled to fill $barcodeWidth
     * (quiet zones included). A path with one subpath per bar — rather than
     * one <rect> each — keeps the markup small; dompdf and browsers both
     * render it.
     */
    private function code128BBarsMarkup(string $data, float $barcodeWidth, int $barcodeHeight): string
    {
        $modules = $this->code128BModules($data);
        $quietZone = self::BARCODE_QUIET_ZONE_MODULES;
        $moduleWidth = $barcodeWidth / (array_sum($modules) + 2 * $quietZone);

        $path = '';
        $x = $quietZone * $moduleWidth;

        foreach ($modules as $index => $width) {
            $barWidth = $width * $moduleWidth;

            if ($index % 2 === 0) {
                $path .= sprintf('M%.3F 0h%.3Fv%dh-%.3FZ', $x, $barWidth, $barcodeHeight, $barWidth);
            }

            $x += $barWidth;
        }

        return '<path fill="#000000" d="'.$path.'"/>';
    }

    /**
     * The product's name for the app's active locale — ar_name under `ar`
     * (falling back to the base name when untranslated, as ar_name is an
     * empty string rather than null then), the base name otherwise. Mirrors
     * resources/js/lib/productName.ts; the other locale's name is never shown.
     */
    private function productLabelName(Product $product): string
    {
        return app()->getLocale() === 'ar' && $product->ar_name !== ''
            ? $product->ar_name
            : $product->name;
    }

    /**
     * The product-id label: the product's id encoded and printed beneath its
     * barcode, with its name below.
     */
    private function productQrLabelImage(Product $product, int $width, int $height): string
    {
        return $this->productLabelImage(
            $product,
            (string) $product->id,
            (string) $product->id,
            $width,
            $height,
        );
    }

    /**
     * The label for one of the product's own stored barcodes (the value a
     * supplier or scanner assigned, see ProductBarcode): that exact value is
     * encoded and printed beneath its bars, with the product's name below.
     *
     * @throws InvalidArgumentException when the value is not printable ASCII
     */
    private function productBarcodeLabelImage(Product $product, string $barcode, int $width, int $height): string
    {
        return $this->productLabelImage($product, $barcode, $barcode, $width, $height);
    }

    /**
     * $width/$height are Setting::current()'s qr_code_width/qr_code_height:
     * the label's total box. The barcode always spans the full width; the
     * large caption and name below it yield to $height like every other
     * label — the name is wrapped and truncated with an ellipsis, then
     * dropped, when it doesn't fit.
     *
     * Code 128 grows with every character, so a long value would squeeze its
     * bars below what a scanner resolves: the canvas widens instead until each
     * module is at least BARCODE_MIN_MODULE_PX wide.
     */
    private function productLabelImage(Product $product, string $encoded, string $caption, int $width, int $height): string
    {
        $padding = self::LABEL_PADDING;
        $modulesWidth = array_sum($this->code128BModules($encoded)) + 2 * self::BARCODE_QUIET_ZONE_MODULES;
        $width = max($width, (int) ceil($modulesWidth * self::BARCODE_MIN_MODULE_PX) + $padding * 2);
        $centerX = (int) ($width / 2);
        $textMaxWidth = $width - $padding * 2;
        $maxTextY = $height - 16;
        $barcodeHeight = max(60, (int) round($width * 0.3));
        $barcodeBottom = $padding + $barcodeHeight;

        $barsMarkup = $this->code128BBarsMarkup($encoded, (float) $width - $padding * 2, $barcodeHeight);

        $idFontSize = self::PRODUCT_LABEL_ID_FONT_SIZE;
        $idLineHeight = (int) round($idFontSize * 1.2);
        $idY = $barcodeBottom + 12 + (int) round($idFontSize * 0.8);
        $idLines = $this->clampLinesToHeight($this->wrapLabelText($caption, $textMaxWidth, $idFontSize), $idY, $idLineHeight, $maxTextY);
        $idMarkup = $this->textLinesMarkup($idLines, $centerX, $idY, $idLineHeight, $idFontSize, '#111111', 'ltr', bold: true);
        // The id block's bottom is its *last* line's baseline: a long value
        // (a stored barcode) wraps onto several lines, and anchoring the name
        // to the first line only would draw the name over the rest.
        $contentBottom = $idLines !== [] ? $idY + (count($idLines) - 1) * $idLineHeight : $barcodeBottom;

        $nameFontSize = self::PRODUCT_LABEL_NAME_FONT_SIZE;
        $nameLineHeight = (int) round($nameFontSize * 1.2);
        $nameY = $contentBottom + ($idLines !== [] ? 16 : 12) + (int) round($nameFontSize * 0.8);
        $nameLines = $this->wrapLabelText($this->productLabelName($product), $textMaxWidth, $nameFontSize);
        $nameLines = $this->limitLabelLines($nameLines, self::PRODUCT_LABEL_NAME_MAX_LINES);
        $nameLines = $this->clampLinesToHeight($nameLines, $nameY, $nameLineHeight, $maxTextY);
        $nameDirection = app()->getLocale() === 'ar' ? 'rtl' : 'ltr';
        $nameMarkup = $this->textLinesMarkup($nameLines, $centerX, $nameY, $nameLineHeight, $nameFontSize, '#111111', $nameDirection, bold: true);

        if ($nameLines !== []) {
            $contentBottom = $nameY + (count($nameLines) - 1) * $nameLineHeight;
        }

        $labelHeight = $contentBottom + 16;

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$labelHeight}" viewBox="0 0 {$width} {$labelHeight}">
                <rect width="100%" height="100%" fill="#ffffff"/>
                <g transform="translate({$padding},{$padding})">{$barsMarkup}</g>
                {$idMarkup}
                {$nameMarkup}
            </svg>
            SVG;
    }
}
