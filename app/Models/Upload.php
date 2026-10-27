<?php

namespace App\Models;

use Database\Factories\UploadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row in the integrated store's `uploads` table on the default connection.
 *
 * Store upload flows persist metadata through services such as
 * MultipartUploadService and its WritesUploads concern. The initial stand-in
 * migration provides paths and timestamps; later migrations add user_id and
 * upload metadata. Warehouse thumbnails read this row through Product.
 * This model does not use SoftDeletes: managed upload deletion removes the
 * file through MultipartUploadService and hard-deletes its metadata row.
 *
 * @property int $id
 * @property string|null $file_original_name
 * @property string|null $file_name
 * @property int|null $user_id
 * @property string|null $extension
 * @property string|null $type
 * @property int|null $file_size
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
     * Uploads whose original name contains `$term`.
     *
     * A `%`, `_` or `\` in the term is escaped so it matches itself rather than
     * acting as a wildcard. Both drivers need the explicit `ESCAPE` clause for
     * that (sqlite has no escape character without one), so this is a raw
     * `like` instead of `where(..., 'like', ...)`, whose fourth argument is the
     * boolean, not an escape character.
     *
     * @param  Builder<Upload>  $query
     * @return Builder<Upload>
     */
    public function scopeOriginalNameContains(Builder $query, string $term): Builder
    {
        return $query->whereRaw('file_original_name like ? escape ?', ['%'.addcslashes($term, '\\%_').'%', '\\']);
    }

    /**
     * The absolute URL for this upload.
     *
     * `external_link` wins when set — the store uses it for files already
     * living on a CDN or object store, where `file_name` is not meaningful.
     * Otherwise the relative `file_name` is joined to the configured
     * store.asset_base_url for the host serving those files. A missing base URL
     * or file name resolves to null.
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
