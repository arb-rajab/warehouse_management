<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton app-wide settings row — at most one ever exists. The same shape
 * as MobileAppVersionRequirement (see SetMinimumAppVersionCommand): current()
 * mirrors its firstOrNew()-with-no-conditions approach rather than
 * firstOrCreate(['id' => 1], ...), since 'id' isn't fillable and mass
 * assigning it on the create path would be silently dropped (or throw, under
 * strict mode) while the row still gets an auto-increment id anyway.
 *
 * @property int $id
 * @property int $qr_code_width
 * @property int $qr_code_height
 */
#[Fillable(['qr_code_width', 'qr_code_height'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $table = 'wms_settings';

    /**
     * qr_code_width/qr_code_height are the label's total box — the QR square
     * plus any text below it, not just the QR itself (see
     * BuildsQrLabels::qrLabelImage()). The QR's own square side is always
     * $qr_code_width minus 2*padding (padding=20), so 280 reproduces the
     * exact 240px QR this app hardcoded before this table existed — that
     * also keeps the text-wrap width (280-40=240) identical to before,
     * which matters: a narrower default here was previously observed to
     * wrap a short cell description ("Row Z · Cell 1 · Level 1", 24 chars)
     * onto two lines where it used to fit on one. 380 leaves comfortable
     * room below the 240px QR for a primary line plus a full, unwrapped
     * secondary line (name + Arabic name) at this trait's font sizes,
     * without hitting the truncation ceiling for ordinary-length text.
     */
    public const int DEFAULT_QR_CODE_WIDTH = 280;

    public const int DEFAULT_QR_CODE_HEIGHT = 380;

    /**
     * Bounds enforced by UpdateSettingRequest on write, and defensively
     * re-applied by the qrCodeWidth()/qrCodeHeight() accessors below on
     * read, so a stored value from before validation existed (or a direct DB
     * edit) can't produce a broken/oversized SVG or blow up dompdf.
     */
    public const int MIN_QR_CODE_SIZE = 100;

    public const int MAX_QR_CODE_SIZE = 1000;

    /**
     * The single settings row, created with today's QR-size defaults the
     * first time anything reads it. Every export/admin read/update goes
     * through this rather than querying wms_settings directly, so there is
     * never more than one row.
     */
    public static function current(): self
    {
        $setting = static::query()->firstOrNew();

        if (! $setting->exists) {
            $setting->qr_code_width = self::DEFAULT_QR_CODE_WIDTH;
            $setting->qr_code_height = self::DEFAULT_QR_CODE_HEIGHT;
            $setting->save();
        }

        return $setting;
    }

    /**
     * Unlike Product::boxesCount() (a derived, relation-backed value that is
     * never assigned directly), qr_code_width is a real fillable column that
     * current() and mass assignment both write to — so the set side is a
     * plain int, not `never`.
     *
     * @return Attribute<int, int>
     */
    protected function qrCodeWidth(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): int => max(self::MIN_QR_CODE_SIZE, min(self::MAX_QR_CODE_SIZE, $value)),
        );
    }

    /**
     * @return Attribute<int, int>
     */
    protected function qrCodeHeight(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): int => max(self::MIN_QR_CODE_SIZE, min(self::MAX_QR_CODE_SIZE, $value)),
        );
    }
}
