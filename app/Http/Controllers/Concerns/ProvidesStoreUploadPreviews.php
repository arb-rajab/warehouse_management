<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Resources\Store\UploadResource;
use App\Models\Upload;
use Illuminate\Http\Request;

trait ProvidesStoreUploadPreviews
{
    /** @return list<UploadResource> */
    private function uploadPreviews(Request $request, ?Upload $upload): array
    {
        $user = $request->user('store');
        if (! $user || ! $upload) {
            return [];
        }

        return ($user->can('view_uploads') || $upload->user_id === $user->id) && $user->can('view', $upload) ? [new UploadResource($upload)] : [];
    }

    /**
     * The previews of stored settings images, keyed by setting: each holds one
     * upload id or comma-separated ids, previewed in stored order as
     * uploadPreviews() allows, in one query.
     *
     * @param  list<string>  $keys
     * @return array<string, list<UploadResource>>
     */
    private function settingsImagePreviews(Request $request, array $keys): array
    {
        $ids = [];
        foreach ($keys as $key) {
            $ids[$key] = array_values(array_filter(explode(',', (string) get_setting($key, '')), ctype_digit(...)));
        }
        $uploads = Upload::query()->select(['id', 'user_id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])
            ->whereIn('id', array_merge(...array_values($ids)))->get()->keyBy('id');

        return array_map(fn (array $keyIds): array => array_merge(...array_map(
            fn (string $id): array => $this->uploadPreviews($request, $uploads->get((int) $id)),
            $keyIds,
        )), $ids);
    }
}
