<?php

namespace App\Services\Store;

use App\Models\Upload;

class TicketAttachments
{
    /** @param list<string|null> $fields
     * @return array<int, array{id: int, name: string, url: string}> */
    public function read(array $fields): array
    {
        $ids = array_values(array_unique(array_merge(...array_map(fn ($value) => $this->ids($value), $fields))));
        $result = [];
        foreach (Upload::query()->select(['id', 'file_original_name', 'extension', 'file_name', 'external_link'])->whereIn('id', $ids)->get() as $upload) {
            // Exactly uploaded_asset()'s existing local/external/S3 resolution.
            $url = $upload->external_link === null ? my_asset($upload->file_name ?? '') : ($upload->file_name === null ? $upload->external_link : $upload->file_name);
            $compact = (string) preg_replace('/[\x00-\x20\x7F-\x9F]+/u', '', $url);
            if ($compact === '' || str_starts_with($compact, '//') || (preg_match('/^[a-z][a-z0-9+.-]*:/i', $compact) === 1 && preg_match('#^https?://#i', $compact) !== 1)) {
                continue;
            }
            $result[$upload->id] = ['id' => $upload->id, 'name' => $upload->file_original_name.'.'.$upload->extension, 'url' => $url];
        }

        return $result;
    }

    /** @return list<int> */
    public function ids(?string $value): array
    {
        return array_values(array_map(intval(...), array_filter(explode(',', $value ?? ''), ctype_digit(...))));
    }

    /** @param array<int, array{id: int, name: string, url: string}> $uploads
     * @return list<array{id: int, name: string, url: string}> */
    public function forFiles(?string $value, array $uploads): array
    {
        return array_values(array_intersect_key($uploads, array_flip($this->ids($value))));
    }
}
