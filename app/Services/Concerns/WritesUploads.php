<?php

namespace App\Services\Concerns;

use App\Models\Upload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Where an upload ends up, for the services that vet one first.
 *
 * Nothing about the path comes from the client: the directory is fixed, the
 * name is random and the extension is the one the service decided from the
 * content. The client's filename survives only as the display name.
 */
trait WritesUploads
{
    /**
     * @param  string  $type  One of image, video, audio, archive, document.
     *
     * @throws InvalidArgumentException When the file cannot be written.
     */
    protected function writeUpload(string $contents, string $extension, string $type, ?string $clientFilename, int $userId, ?string $mimeType = null): Upload
    {
        $path = 'uploads/all/'.Str::random(40).'.'.$extension;

        if (config('filesystems.default') === 's3') {
            if (! Storage::disk('s3')->put($path, $contents, array_filter(['visibility' => 'public', 'ContentType' => $mimeType]))) {
                throw new InvalidArgumentException('Uploading error');
            }
        } else {
            File::ensureDirectoryExists(public_path('uploads/all'));

            if (file_put_contents(public_path($path), $contents, LOCK_EX) === false) {
                throw new InvalidArgumentException('Uploading error');
            }
        }

        $upload = new Upload;
        $upload->file_original_name = $this->displayName($clientFilename);
        $upload->extension = $extension;
        $upload->file_name = $path;
        $upload->user_id = $userId;
        $upload->type = $type;
        $upload->file_size = strlen($contents);
        $upload->save();

        return $upload;
    }

    /**
     * The client's filename without its extension, for display only: it is
     * never used to build a path.
     */
    protected function displayName(?string $clientFilename): ?string
    {
        $name = pathinfo(basename(str_replace('\\', '/', (string) $clientFilename)), PATHINFO_FILENAME);
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name));

        return $name === '' ? null : mb_substr($name, 0, 255);
    }
}
