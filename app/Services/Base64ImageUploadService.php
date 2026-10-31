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
        if (strlen((string) $base64) > (int) (ceil(self::MAX_BYTES / 3) * 4)) {
            throw new InvalidArgumentException('The image is too large.');
        }

        $decoded = base64_decode((string) $base64, true);

        if ($decoded === false || $decoded === '') {
            throw new InvalidArgumentException('The image is not valid base64.');
        }

        if (strlen($decoded) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The image is too large.');
        }

        [$contents, $extension] = $this->normalise($decoded);

        return $this->writeUpload($contents, $extension, 'image', $clientFilename, $userId, $extension === 'svg' ? 'image/svg+xml' : ($extension === 'jpg' ? 'image/jpeg' : 'image/'.$extension));
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
            return app(ImageOptimizer::class)->optimise($decoded);
        }

        if (str_contains(strtolower(substr($decoded, 0, 4096)), '<svg')) {
            $clean = (new Sanitizer)->sanitize($decoded);

            if (! is_string($clean) || $clean === '' || strlen($clean) > ImageOptimizer::MAX_SVG_BYTES) {
                throw new InvalidArgumentException('The image is not valid.');
            }

            return [$clean, 'svg'];
        }

        throw new InvalidArgumentException('Only image can be uploaded');
    }
}
