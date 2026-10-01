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
 * Where a document the shop keeps on its private disk goes, and how it comes
 * back: the base of the trade-licence and offers-catalog storages, which say
 * which directory, which kinds of file and which form field they are.
 *
 * These were saved as `<directory>/<the client's filename>`: one upload
 * overwrote another of the same name, nothing looked at what the file was, and
 * the downloads built their path from the request or from a public path the file
 * was never written to. The name is random now, the extension comes from the
 * content, and a name read back is reduced to its basename before it is looked
 * up. A file is only ever handed out by a controller that has checked who is
 * asking.
 */
abstract class PrivateDocumentStorage
{
    /** Where on the private disk the files go. */
    protected const DIRECTORY = '';

    /** The extensions a file may have, as its content reports them (a JPEG is `jpg`). */
    protected const EXTENSIONS = [];

    /** The request field, which the validation error is reported on. */
    protected const FIELD = '';

    /** What the person sees when a file is refused. */
    protected const REFUSAL = '';

    protected const MAX_BYTES = 20 * 1024 * 1024;

    /**
     * The name to keep on the record.
     *
     * @throws ValidationException When the file is too large or not one of the allowed kinds.
     */
    public function store(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $extension = $path === false || $file->getSize() > static::MAX_BYTES ? null : $this->extensionOf($path, strtolower($file->getClientOriginalExtension()));

        if ($extension === null) {
            throw ValidationException::withMessages([static::FIELD => translate(static::REFUSAL)]);
        }

        $name = Str::random(40).'.'.$extension;
        Storage::disk('local')->put(static::DIRECTORY.'/'.$name, (string) file_get_contents($path));

        return $name;
    }

    /**
     * Remove a stored file; a name that is not there is not an error.
     */
    public function delete(?string $name): void
    {
        $name = $this->safeName($name);

        if ($name !== null) {
            Storage::disk('local')->delete(static::DIRECTORY.'/'.$name);
        }
    }

    /**
     * The response that sends the stored file, or null when there is none by
     * that name (an empty or traversing name finds nothing).
     */
    public function download(?string $name): ?BinaryFileResponse
    {
        $name = $this->safeName($name);
        $relative = static::DIRECTORY.'/'.$name;

        if ($name === null || ! Storage::disk('local')->exists($relative)) {
            return null;
        }

        return response()->download(Storage::disk('local')->path($relative), $name, ['X-Content-Type-Options' => 'nosniff']);
    }

    /** The name cut to its last path segment, or null when nothing usable is left. */
    private function safeName(?string $name): ?string
    {
        $name = basename(str_replace('\\', '/', (string) $name));

        return in_array($name, ['', '.', '..'], true) ? null : $name;
    }

    /**
     * The extension to store, from what the file is, or null when it is not one
     * of the allowed kinds.
     */
    private function extensionOf(string $path, string $clientExtension): ?string
    {
        $mimeType = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null;

        // Some detectors call a .docx a plain zip; accept that only if it holds a Word document.
        if ($extension === 'zip' && $clientExtension === 'docx' && $this->holdsWordDocument($path)) {
            return 'docx';
        }

        return in_array($extension, static::EXTENSIONS, true) ? $extension : null;
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
