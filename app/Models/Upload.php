<?php

namespace App\Models;

use Database\Factories\UploadFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row in the store app's shared `uploads` table.
 *
 * The store's own upload flow (AizUploadController, the import commands) is
 * the writer — see .ai/rules/legacy-albaraka-holland.md section 2 — with
 * $fillable merged in from the legacy model for that, though none of those
 * extra columns exist in this app's local `uploads` stand-in migration yet
 * (it deliberately carries only the 3 columns this app itself reads). The
 * legacy model's SoftDeletes trait is dropped for the same reason: it adds a
 * global `deleted_at` scope to every query, and that column doesn't exist
 * here either — resolving both is DB-merge-phase work, not this move.
 *
 * @property int $id
 * @property string|null $file_name
 * @property string|null $external_link
 * @property-read string|null $url
 */
class Upload extends Model
{
    /** @use HasFactory<UploadFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'file_original_name', 'file_name', 'user_id', 'extension', 'type', 'file_size',
    ];

    /**
     * The absolute URL for this upload.
     *
     * `external_link` wins when set — the store uses it for files already
     * living on a CDN or object store, where `file_name` is not meaningful.
     * Otherwise the relative `file_name` is joined to the store app's public
     * base URL, which this app cannot derive on its own because it is served
     * from a different host. A missing base URL resolves to null rather than
     * to a relative path that would 404 against this app's own domain.
     *
     * @return Attribute<string|null, never>
     */
    protected function url(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            if (filled($this->external_link)) {
                return $this->external_link;
            }

            $baseUrl = config('store.asset_base_url');

            if (blank($baseUrl) || blank($this->file_name)) {
                return null;
            }

            return rtrim((string) $baseUrl, '/').'/'.ltrim($this->file_name, '/');
        });
    }

    /**
     * @return BelongsTo<StoreUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class);
    }
}
