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

    /**
     * An unchanged stored image may round-trip without passing the owner check
     * again; any other reference must satisfy imageUploadRule().
     */
    protected function retainableImageUploadRule(string $field, int|string|null $current): Exists
    {
        $value = $this->input($field);
        $retained = (is_int($value) || is_string($value)) && $current !== null && (string) $value === (string) $current;

        return $retained ? Rule::exists('uploads', 'id')->where('type', 'image') : $this->imageUploadRule();
    }
}
