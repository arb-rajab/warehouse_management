<?php

namespace App\Http\Requests\Concerns;

use App\Models\Upload;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** @mixin FormRequest */
trait ValidatesStoreUploadReference
{
    protected function imageUploadRule(): Exists
    {
        return $this->uploadReferenceRule('image');
    }

    /**
     * An upload of `$type` (any type when null) that the caller owns, or any
     * such upload when the caller may browse the library.
     */
    protected function uploadReferenceRule(?string $type): Exists
    {
        $upload = Rule::exists('uploads', 'id');
        if ($type !== null) {
            $upload->where('type', $type);
        }
        if ($this->refusesLibraryUploads()) {
            $upload->where('user_id', $this->user('store')->id);
        }

        return $upload;
    }

    /**
     * An unchanged stored image may round-trip without passing the owner check
     * again; any other reference must satisfy imageUploadRule().
     */
    protected function retainableImageUploadRule(string $field, int|string|null $current): Exists
    {
        return $this->retainableUploadReferenceRule($field, $current, 'image');
    }

    /**
     * retainableImageUploadRule() for an upload of `$type`, or of any type
     * when null (a digital product's downloadable file).
     */
    protected function retainableUploadReferenceRule(string $field, int|string|null $current, ?string $type): Exists
    {
        $value = $this->input($field);
        $retained = (is_int($value) || is_string($value)) && $current !== null && (string) $value === (string) $current;
        if (! $retained) {
            return $this->uploadReferenceRule($type);
        }
        $upload = Rule::exists('uploads', 'id');

        return $type === null ? $upload : $upload->where('type', $type);
    }

    /**
     * Comma-separated image upload ids, as the legacy uploader stored them (at
     * most 50, no repeats). An id already in `$stored` round-trips whoever
     * owns it, without granting a preview; any other must pass the library
     * rule of imageUploadRule(). With ownOnly, new references always belong
     * to the caller, even when their role grants library access.
     */
    protected function retainableImageUploadIdsRule(?string $stored, bool $ownOnly = false): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($stored, $ownOnly): void {
            if (! is_string($value) || preg_match('/^\d+(?:,\d+)*$/', $value) !== 1) {
                $fail(__('validation.regex', ['attribute' => $attribute]));

                return;
            }
            $ids = explode(',', $value);
            $storedIds = explode(',', $stored ?? '');
            $images = Upload::query()->where('type', 'image');
            $found = (clone $images)->whereIn('id', array_intersect($ids, $storedIds))->count()
                + (clone $images)->whereIn('id', array_diff($ids, $storedIds))
                    ->when($ownOnly || $this->refusesLibraryUploads(), fn ($query) => $query->where('user_id', $this->user('store')->id))
                    ->count();
            if (count($ids) > 50 || count(array_unique($ids)) !== count($ids) || $found !== count($ids)) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }

    /** Whether the store caller is limited to their own uploads. */
    private function refusesLibraryUploads(): bool
    {
        $user = $this->user('store');

        return $user && (! $user->can('view_uploads') || ! $user->can('viewAny', Upload::class));
    }
}
