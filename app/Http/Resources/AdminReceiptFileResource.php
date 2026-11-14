<?php

namespace App\Http\Resources;

/**
 * A bill file as the admin web pages see it: `url` is the session-authenticated
 * `admin.receipts.files.show` route (the API one needs a bearer token). Still
 * never the storage path.
 */
class AdminReceiptFileResource extends ReceiptFileResource
{
    protected function downloadUrl(): string
    {
        return route('admin.receipts.files.show', ['receipt' => $this->receipt_id, 'file' => $this->id]);
    }
}
