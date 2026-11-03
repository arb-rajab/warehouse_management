<?php

namespace App\Services;

use App\Models\Upload;
use Intervention\Image\ImageManagerStatic as Image;
use Throwable;

/**
 * A small copy of a store upload for the product lists. `thumbnail_image` used
 * to point at the original upload, so a page of ten products could weigh 5 MB
 * (two PNGs of 1.6 MB and 1.7 MB). The copy is made the first time it is asked
 * for, written beside the uploads under `uploads/thumbs`, and made again if the
 * original is replaced. The original stays where it is, in `photos[].path`.
 *
 * Uploads that are not files on this server (an external link, S3) and files
 * that cannot be read or resized keep their original URL.
 */
class ThumbnailService
{
    public const WIDTH = 400;

    private const DIRECTORY = 'uploads/thumbs';

    /** Resizing a file this big could exhaust memory: it keeps its original. */
    private const MAX_SOURCE_PIXELS = 40_000_000;

    /**
     * The public URL of the upload's thumbnail, or null when it has none.
     */
    public function urlFor(Upload $asset, int $width = self::WIDTH): ?string
    {
        if ($asset->external_link !== null || config('filesystems.default') === 's3' || blank($asset->file_name)) {
            return null;
        }

        $source = public_path($asset->file_name);

        if (! is_file($source)) {
            return null;
        }

        $format = function_exists('imagewebp') ? 'webp' : 'jpg';
        $relative = self::DIRECTORY.'/'.$asset->id.'-'.$width.'.'.$format;
        $target = public_path($relative);

        if (! is_file($target) || filemtime($target) < filemtime($source)) {
            if (! $this->make($source, $target, $format, $width)) {
                return null;
            }
        }

        return my_asset($relative);
    }

    private function make(string $source, string $target, string $format, int $width): bool
    {
        $info = @getimagesize($source);

        // SVG and other non-raster files, and files that are not images, keep the original.
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            return false;
        }

        // A file already within the width is small enough as it is.
        if ($info[0] <= $width && filesize($source) <= 150 * 1024) {
            return false;
        }

        if ($info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            return false;
        }

        $image = null;

        try {
            if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
                return false;
            }

            $image = Image::make($source)->resize($width, null, function ($constraint): void {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Written to a temporary name first: a request reading it meanwhile never sees half a file.
            $temporary = $target.'.'.getmypid().'.tmp';
            file_put_contents($temporary, (string) $image->encode($format, 80));

            return rename($temporary, $target);
        } catch (Throwable) {
            return false;
        } finally {
            $image?->destroy();
        }
    }
}
