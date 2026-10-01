<?php

namespace App\Services;

use App\Models\Upload;
use App\Services\Concerns\WritesUploads;
use enshrined\svgSanitize\Sanitizer;
use InvalidArgumentException;

/**
 * Stores an image the mobile app sends as base64, for the store's API.
 *
 * Those endpoints used to write the decoded bytes to `uploads/all/<filename>`,
 * with the filename taken from the request, and look at the extension only
 * afterwards, deleting the file again if it was not an image. A `../` in the
 * name wrote (and then deleted) a file anywhere the web user could reach,
 * `index.php` included, and the PHP file existed on disk until the check ran.
 *
 * Nothing here trusts the client's name or its claim about the type: the bytes
 * are decoded strictly and sized up, the type is read from the content, raster
 * images are re-encoded (which drops anything appended to or hidden in them),
 * SVG goes through the sanitiser, and the one file written has a server-chosen
 * name and extension. The client's filename is kept only as the display name.
 */
class Base64ImageUploadService
{
    use WritesUploads;

    /** The largest decoded image accepted, in bytes. */
    public const MAX_BYTES = 8 * 1024 * 1024;

    /** The most pixels a raster image may hold, so a tiny file cannot expand into gigabytes. */
    private const MAX_PIXELS = 40_000_000;

    private const RASTER_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * @throws InvalidArgumentException With a message that is safe to show the caller.
     */
    public function store(?string $base64, ?string $clientFilename, int $userId): Upload
    {
        $decoded = base64_decode((string) $base64, true);

        if ($decoded === false || $decoded === '') {
            throw new InvalidArgumentException('The image is not valid base64.');
        }

        if (strlen($decoded) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The image is too large.');
        }

        [$contents, $extension] = $this->normalise($decoded);

        return $this->writeUpload($contents, $extension, 'image', $clientFilename, $userId);
    }

    /**
     * The bytes to store and their extension, from what the content is.
     *
     * @return array{0: string, 1: string}
     */
    private function normalise(string $decoded): array
    {
        $info = @getimagesizefromstring($decoded);

        if ($info !== false && isset(self::RASTER_EXTENSIONS[$info['mime']])) {
            return [$this->reencode($decoded, $info), self::RASTER_EXTENSIONS[$info['mime']]];
        }

        if (str_contains(strtolower(substr($decoded, 0, 4096)), '<svg')) {
            $clean = (new Sanitizer)->sanitize($decoded);

            if (! is_string($clean) || $clean === '') {
                throw new InvalidArgumentException('The image is not valid.');
            }

            return [$clean, 'svg'];
        }

        throw new InvalidArgumentException('Only image can be uploaded');
    }

    /**
     * @param  array<int|string, mixed>  $info
     */
    private function reencode(string $decoded, array $info): string
    {
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('The image is too large.');
        }

        $image = @imagecreatefromstring($decoded);

        if ($image === false) {
            throw new InvalidArgumentException('The image is not valid.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();

        match ($info['mime']) {
            'image/jpeg' => imagejpeg($image, null, 90),
            'image/png' => imagepng($image),
            'image/gif' => imagegif($image),
            'image/webp' => imagewebp($image, null, 90),
        };

        return (string) ob_get_clean();
    }
}
