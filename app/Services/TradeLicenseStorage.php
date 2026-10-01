<?php

namespace App\Services;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mime\MimeTypes;
use ZipArchive;

/**
 * Where a customer's trade licence goes, and how it comes back.
 *
 * A licence is a business document, so it lives on the private disk and is only
 * ever handed out by a controller that has checked who is asking. It used to be
 * saved as `uploads/licenses/<the client's filename>`: one customer's upload
 * overwrote another's of the same name, nothing looked at what the file was
 * (the store's own registration and profile forms never validated it), and the
 * admin download built its path from the request. The name is random now, the
 * extension comes from the content, and a name read back is reduced to its
 * basename before it is looked up.
 */
class TradeLicenseStorage
{
    private const DIRECTORY = 'uploads/licenses';

    private const MAX_BYTES = 20 * 1024 * 1024;

    private const EXTENSIONS = ['pdf', 'doc', 'docx', 'png', 'jpg'];

    /**
     * The name to keep on the customer's record.
     *
     * @throws ValidationException When the file is too large or not a PDF, Word document or PNG/JPEG image.
     */
    public function store(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $extension = $path === false || $file->getSize() > self::MAX_BYTES ? null : $this->extensionOf($path, strtolower($file->getClientOriginalExtension()));

        if ($extension === null) {
            throw ValidationException::withMessages([
                'trade_license' => translate('The trade license must be a PDF, Word document or PNG/JPEG image of at most 20 MB.'),
            ]);
        }

        $name = Str::random(40).'.'.$extension;
        Storage::disk('local')->put(self::DIRECTORY.'/'.$name, (string) file_get_contents($path));

        return $name;
    }

    /**
     * The response that sends the stored licence, or null when there is none by
     * that name (an empty or traversing name finds nothing).
     */
    public function download(?string $name): ?BinaryFileResponse
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        $relative = self::DIRECTORY.'/'.$name;

        if ($name === '' || $name === '.' || $name === '..' || ! Storage::disk('local')->exists($relative)) {
            return null;
        }

        return response()->download(Storage::disk('local')->path($relative), $name, ['X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * The extension to store, from what the file is: a PDF, a Word document or a
     * PNG/JPEG image, else null.
     */
    private function extensionOf(string $path, string $clientExtension): ?string
    {
        $mimeType = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null;

        // Some detectors call a .docx a plain zip; accept that only if it holds a Word document.
        if ($extension === 'zip' && $clientExtension === 'docx' && $this->holdsWordDocument($path)) {
            return 'docx';
        }

        return in_array($extension, self::EXTENSIONS, true) ? $extension : null;
    }

    private function holdsWordDocument(string $path): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return false;
        }

        $found = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $found;
    }
}
