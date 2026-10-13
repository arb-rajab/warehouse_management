<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Opt-in optimistic concurrency: a client that sends `If-Match` with the ETag
 * it last read is refused (412) when the resource has changed since. A request
 * without the header behaves exactly as before. The controller only parses the
 * header; the comparison happens in the service, under the row locks.
 */
trait ChecksIfMatch
{
    /**
     * The strong ETags the client will accept, or null when it set no precondition
     * (no header, or `*`). A weak `W/"…"` candidate can never match (RFC 9110
     * strong comparison), so it is dropped; a header holding only such tags
     * yields an empty list, which matches nothing.
     *
     * @return list<string>|null
     */
    private function ifMatchEtags(Request $request): ?array
    {
        $header = $request->header('If-Match');

        if ($header === null || trim($header) === '*') {
            return null;
        }

        $etags = [];

        foreach (explode(',', $header) as $candidate) {
            if (preg_match('/^"([^"]*)"$/', trim($candidate), $matches) === 1) {
                $etags[] = $matches[1];
            }
        }

        return $etags;
    }
}
