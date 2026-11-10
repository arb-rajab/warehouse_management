<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reads one bill file for the admin pages (session-authenticated, `role:admin`).
 * The bytes live on a private disk and are never reachable by a path.
 */
class ReceiptFileController extends Controller
{
    /**
     * The bill file inline (thumbnail or PDF), 404 when its bytes are gone or
     * the file belongs to another receipt (scoped binding).
     */
    public function show(Receipt $receipt, ReceiptFile $file): StreamedResponse
    {
        return $file->inlineResponse();
    }
}
