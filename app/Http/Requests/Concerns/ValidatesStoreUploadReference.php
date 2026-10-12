<?php

namespace App\Http\Requests\Concerns;

use App\Models\Upload;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ValidatesStoreUploadReference
{
    protected function imageUploadRule(): Exists
    {
        $image = Rule::exists('uploads', 'id')->where('type', 'image');
        $user = $this->user('store');

        if ($user && (! $user->can('view_uploads') || ! $user->can('viewAny', Upload::class))) {
            $image->where('user_id', $user->id);
        }

        return $image;
    }
}
