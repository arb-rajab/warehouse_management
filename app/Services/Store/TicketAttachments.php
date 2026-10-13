<?php

namespace App\Services\Store;

use App\Http\Resources\Store\UploadResource;
use App\Models\Upload;
use Illuminate\Support\Collection;

/**
 * Resolves the comma-separated upload ids a ticket or ticket reply stores in
 * `files` into the canonical upload contract (id, name, type, size, url).
 *
 * A ticket's files are part of the ticket: everyone who may open the ticket
 * sees them, whoever uploaded them, as the Blade page showed them. That does
 * not open them to the upload library's previews or to a new reply, which
 * keep the library permission and ownership checks.
 */
class TicketAttachments
{
    /**
     * Loads every upload the given `files` values name, in one query.
     *
     * @param  list<string|null>  $fields
     * @return Collection<int, Upload> keyed by id
     */
    public function read(array $fields): Collection
    {
        $ids = array_values(array_unique(array_merge([], ...array_map($this->ids(...), $fields))));

        return Upload::query()
            ->select(['id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])
            ->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * The ids a stored `files` value names, in their stored order. Blank,
     * non-numeric and repeated entries are skipped.
     *
     * @return list<int>
     */
    public function ids(?string $value): array
    {
        $ids = array_map(intval(...), array_filter(array_map(trim(...), explode(',', $value ?? '')), ctype_digit(...)));

        return array_values(array_unique($ids));
    }

    /**
     * The attachments of one `files` value, skipping ids whose upload is gone.
     *
     * @param  Collection<int, Upload>  $uploads
     * @return list<UploadResource>
     */
    public function forFiles(?string $value, Collection $uploads): array
    {
        $attachments = [];
        foreach ($this->ids($value) as $id) {
            if ($uploads->has($id)) {
                $attachments[] = new UploadResource($uploads->get($id));
            }
        }

        return $attachments;
    }
}
