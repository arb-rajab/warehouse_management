<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ValidatesStoreUploadReference
{
    protected function imageUploadRule(): Exists
    {
        $image = Rule::exists('uploads', 'id')->where('type', 'image');
        $user = $this->user('store');

        if ($user && ! in_array($user->user_type, ['admin', 'staff'], true)) {
            $image->where('user_id', $user->id);
        }

        return $image;
    }
}
