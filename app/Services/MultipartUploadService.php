<?php

namespace App\Services;

use App\Models\Upload;
use App\Services\Concerns\WritesUploads;
use enshrined\svgSanitize\Sanitizer;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Stores a file the store's uploader (the web admin and seller screens and the
 * two mobile APIs) receives as a multipart upload.
 *
 * The three controllers each carried a copy of this that trusted the client's
 * extension for the allowlist and let Laravel name the stored file after what
 * the *content* looked like. A file called `a.png` that held HTML was therefore
 * stored as `<random>.html`, and one that held an SVG as `<random>.svg` (only the
 * web copy sanitised an SVG, and only when the client said `.svg`), served from
 * the shop's own origin: script that ran as the shop for whoever opened the
 * link, an admin included. Uploads also went to `storage/app/private`, from where
 * nothing is served.
 *
 * Here the client's extension only picks the kind, and the stored extension
 * comes from the content: raster images must decode and meet the image limits;
 * anything else keeps the allowlisted extension the client named,
 * unless it holds markup a browser would run (HTML, XHTML, scripts), which is
 * refused; SVG is sanitised wherever it turns up.
 */
class MultipartUploadService
{
    use WritesUploads;

    /** The extensions the uploader accepts, and the kind of file each is. */
    public const KINDS = [
        'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'svg' => 'image', 'webp' => 'image', 'gif' => 'image',
        'mp4' => 'video', 'mpg' => 'video', 'mpeg' => 'video', 'webm' => 'video', 'ogg' => 'video', 'avi' => 'video',
        'mov' => 'video', 'flv' => 'video', 'mkv' => 'video', 'wmv' => 'video',
        'wma' => 'audio', 'aac' => 'audio', 'wav' => 'audio', 'mp3' => 'audio',
        'zip' => 'archive', 'rar' => 'archive', '7z' => 'archive',
        'doc' => 'document', 'txt' => 'document', 'docx' => 'document', 'pdf' => 'document', 'csv' => 'document',
        'ods' => 'document', 'xlr' => 'document', 'xls' => 'document', 'xlsx' => 'document',
    ];

    /** Extensions of content a browser runs when it is served from the shop. */
    private const ACTIVE_CONTENT_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'shtml', 'js', 'mjs', 'php', 'phtml', 'phar', 'hta', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py', 'sh',
    ];

    /** Maximum file size in bytes (512 MB). */
    public const MAX_BYTES = 512 * 1024 * 1024;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    /**
     * The upload, or null when the file is not one the uploader accepts (the
     * callers answer a refused file as they always have, without an error).
     */
    public function store(UploadedFile $file, int $userId, bool $normalizeRaster = false): ?Upload
    {
        $clientExtension = strtolower($file->getClientOriginalExtension());
        $kind = self::KINDS[$clientExtension] ?? null;
        $path = $file->getRealPath();

        if ($kind === null || $path === false || $file->getSize() > ($kind === 'image' ? ImageOptimizer::MAX_INPUT_BYTES : self::MAX_BYTES)) {
            return null;
        }

        $contents = (string) file_get_contents($path);
        $mimeType = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $guessed = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null;

        if (in_array($guessed, self::ACTIVE_CONTENT_EXTENSIONS, true)) {
            return null;
        }

        $extension = $this->storedExtension($kind, $clientExtension, $guessed, $mimeType);

        $type = $extension === 'svg' ? 'image' : $kind;

        if ($extension === null || ! $this->mimeTypeMatchesKind($mimeType, $type, $extension)) {
            return null;
        }

        try {
            if ($extension === 'svg') {
                if (strlen($contents) > ImageOptimizer::MAX_INPUT_BYTES) {
                    return null;
                }
                $contents = (string) (new Sanitizer)->sanitize($contents);
                if ($contents === '' || strlen($contents) > ImageOptimizer::MAX_SVG_BYTES) {
                    return null;
                }
            } elseif ($kind === 'image') {
                [$contents, $extension] = app(ImageOptimizer::class)->optimise($contents, $normalizeRaster ? 'webp' : null);
                $mimeType = $extension === 'jpg' ? 'image/jpeg' : 'image/'.$extension;
            }
        } catch (InvalidArgumentException) {
            return null;
        }

        try {
            return $this->writeUpload($contents, $extension, $type, $file->getClientOriginalName(), $userId, $extension === 'svg' ? 'image/svg+xml' : $mimeType);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function storedExtension(string $kind, string $clientExtension, ?string $guessed, string $mimeType): ?string
    {
        if ($kind === 'image') {
            return str_starts_with($mimeType, 'image/') && in_array($guessed, self::IMAGE_EXTENSIONS, true) ? $guessed : null;
        }

        // An SVG that the client called something else is still an SVG to a browser.
        return $guessed === 'svg' ? 'svg' : $clientExtension;
    }

    private function mimeTypeMatchesKind(string $mimeType, string $kind, string $extension): bool
    {
        return match ($kind) {
            'image' => str_starts_with($mimeType, 'image/'),
            'video', 'audio' => str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/') || $mimeType === 'application/ogg',
            'archive' => in_array($mimeType, ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'], true),
            'document' => $this->documentMimeTypeMatches($mimeType, $extension),
            default => false,
        };
    }

    /**
     * A PDF must be one and a text file must be text. The office formats keep
     * the broad check: `finfo` reports OOXML and OLE files as zip, CDFV2 or
     * octet-stream depending on the file, and a spreadsheet export that is
     * really CSV or HTML is common, so a stricter list would refuse real files.
     */
    private function documentMimeTypeMatches(string $mimeType, string $extension): bool
    {
        return match ($extension) {
            'pdf' => $mimeType === 'application/pdf',
            'txt', 'csv' => str_starts_with($mimeType, 'text/') || in_array($mimeType, ['application/csv', 'application/json', 'application/x-empty'], true),
            default => str_starts_with($mimeType, 'text/') || str_starts_with($mimeType, 'application/'),
        };
    }

    /** Remove a managed file before removing its row; never follow a stored path outside uploads/all. */
    public function delete(Upload $upload): void
    {
        $path = $upload->file_name;
        if (blank($upload->external_link) && $path !== null && preg_match('#^uploads/all/[^/\\\\]+$#D', $path) === 1) {
            if (config('filesystems.default') === 's3') {
                if (! Storage::disk('s3')->delete($path)) {
                    throw new InvalidArgumentException('Deleting error');
                }
            } elseif (is_file(public_path($path)) && ! unlink(public_path($path))) {
                throw new InvalidArgumentException('Deleting error');
            }
        }
        $upload->delete();
    }
}
