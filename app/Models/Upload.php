<?php

namespace App\Models;

use Database\Factories\UploadFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A row in the store app's shared `uploads` table.
 *
 * Read-only here, like Product: the store owns every write. Only the three
 * columns needed to build an image URL are modelled — see
 * .ai/rules/shared-database.md.
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
        return Attribute::get(function (): ?string {
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
}
