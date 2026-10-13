<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\PreconditionFailedException;
use Illuminate\Http\Request;

/**
 * Opt-in optimistic concurrency: a client that sends `If-Match` with the ETag
 * it last read is refused (412) when the resource has changed since. A request
 * without the header behaves exactly as before.
 */
trait ChecksIfMatch
{
    /**
     * @throws PreconditionFailedException
     */
    private function ensureIfMatch(Request $request, string $etag): void
    {
        $header = $request->header('If-Match');

        if ($header === null || trim($header) === '*') {
            return;
        }

        // Strong comparison (RFC 9110): a weak `W/"…"` candidate never matches.
        foreach (explode(',', $header) as $candidate) {
            if (trim($candidate) === '"'.$etag.'"') {
                return;
            }
        }

        throw new PreconditionFailedException;
    }
}
