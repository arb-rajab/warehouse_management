<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
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
 * @property list<int> $allowed_open_levels
 */
#[Fillable(['qr_code_width', 'qr_code_height', 'allowed_open_levels'])]
#[Table('wms_settings')]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * qr_code_width/qr_code_height are the label's total box — the QR square
     * plus any text below it, not just the QR itself (see
     * BuildsQrLabels::qrLabelImage()). These were raised from this table's
     * original 280x380 (which only reproduced the 240px QR this app
     * hardcoded before the table existed) once a worker scanning from
     * 1-3m away with a phone camera turned out not to reliably resolve a
     * ~7.4cm-wide QR — the common rule of thumb for phone-camera QR
     * scanning is roughly a 10:1 distance:size ratio, which wants a QR
     * closer to 20-30cm wide for that range.
     *
     * 850 keeps the QR's own square side (qr_code_width - 2*padding,
     * padding=20 — see BuildsQrLabels::qrLabelImage()) at 810px (~21.4cm),
     * comfortably inside the confirmed-workable 20-27cm physical label
     * size, while 1000 (the maximum this field allows at all — see
     * MAX_QR_CODE_SIZE) leaves ~154px of headroom below the QR for the
     * primary slot label to grow into (BuildsCellQrLabels' expandPrimaryText,
     * via pickExpandedPrimaryFontSize()) without needing qrLabelImage()'s
     * QR-shrink safety net. Pushing qr_code_width closer to the 1000 ceiling
     * to chase the full 30cm figure would eat into that headroom and shrink
     * the label back down — the QR's scan distance and the label's read
     * distance are competing for the same fixed 1000px ceiling, and this
     * split favors a QR that's clearly the harder of the two to satisfy
     * (a label read from 1-3m only needs ~1-2cm tall text, well within
     * what 1000-810=190px of budget already provides).
     */
    public const int DEFAULT_QR_CODE_WIDTH = 850;

    public const int DEFAULT_QR_CODE_HEIGHT = 1000;

    /**
     * Bounds enforced by UpdateSettingRequest on write, and defensively
     * re-applied by the qrCodeWidth()/qrCodeHeight() accessors below on
     * read, so a stored value from before validation existed (or a direct DB
     * edit) can't produce a broken/oversized SVG or blow up dompdf.
     */
    public const int MIN_QR_CODE_SIZE = 100;

    public const int MAX_QR_CODE_SIZE = 1000;

    /**
     * The cell levels (`cells.flat_number`) pallets may be opened on until an
     * admin says otherwise: only the ground level, which is also the one
     * level PalletActionService opens automatically.
     *
     * @var list<int>
     */
    public const array DEFAULT_ALLOWED_OPEN_LEVELS = [1];

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
            $setting->allowed_open_levels = self::DEFAULT_ALLOWED_OPEN_LEVELS;
            $setting->save();
        }

        return $setting;
    }

    /**
     * Whether pallets sitting on the given level may be opened. A read-only
     * lookup (unlike current(), it never creates the settings row), used from
     * inside PalletActionService's transactions.
     */
    public static function allowsOpeningAtLevel(int $level): bool
    {
        $setting = static::query()->first(['id', 'allowed_open_levels']);

        return in_array($level, $setting->allowed_open_levels ?? self::DEFAULT_ALLOWED_OPEN_LEVELS, true);
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

    /**
     * Stored as a JSON list; written sorted and de-duplicated as ints, so
     * whatever a caller submits is normalized before it is persisted. A NULL
     * column reads back as the default.
     *
     * @return Attribute<list<int>, list<int|string>>
     */
    protected function allowedOpenLevels(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null
                ? self::DEFAULT_ALLOWED_OPEN_LEVELS
                : array_values(array_map(intval(...), json_decode($value, true, 512, JSON_THROW_ON_ERROR))),
            set: function (array $levels): string {
                $normalized = array_values(array_unique(array_map(intval(...), $levels)));
                sort($normalized);

                return json_encode($normalized, JSON_THROW_ON_ERROR);
            },
        );
    }
}
