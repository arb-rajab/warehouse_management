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
}
