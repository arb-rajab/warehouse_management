/**
 * The Settings > QR code width/height form (SettingFormFields.vue) is
 * cm-facing, but `Setting::qr_code_width`/`qr_code_height` (app/Models/Setting.php)
 * stay integer pixels — the SVG/dompdf QR rendering in
 * app/Http/Controllers/Concerns/BuildsQrLabels.php needs plain pixels, so the
 * conversion lives only at this UI boundary. Uses the standard CSS/print
 * reference pixel (96px = 1in = 2.54cm).
 */
const PX_PER_CM = 96 / 2.54;

export function pxToCm(px: number): number {
    return Math.round((px / PX_PER_CM) * 10) / 10;
}

export function cmToPx(cm: number): number {
    return Math.round(cm * PX_PER_CM);
}
