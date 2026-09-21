<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal application-level web application firewall: blocks any request
 * whose URI, headers or query/body input matches a known attack signature
 * (SQL injection, XSS, path traversal, command injection) from config/waf.php,
 * before it ever reaches routing or a controller.
 */
class BlockMaliciousRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('waf.enabled')) {
            return $next($request);
        }

        foreach (config('waf.exclude_paths', []) as $excludedPath) {
            if ($request->is($excludedPath)) {
                return $next($request);
            }
        }

        $subject = $this->inspectionSubject($request);

        foreach (config('waf.patterns', []) as $category => $patterns) {
            foreach ($patterns as $pattern) {
                $matched = preg_match($pattern, $subject);

                // preg_match() returns false (not 0) when PCRE gives up — a
                // backtrack/recursion limit blown by a large padded payload,
                // for instance. Treating that as "no match" would let an
                // attacker switch the check off by making it expensive, so
                // an engine failure blocks the request just like a match does.
                if ($matched === false) {
                    $this->blockRequest($request, (string) $category, 'pattern_error');
                }

                if ($matched === 1) {
                    $this->blockRequest($request, (string) $category, 'signature_match');
                }
            }
        }

        return $next($request);
    }

    /**
     * Build the single string the attack signatures are matched against.
     *
     * Covers the request URI in both its raw and percent-decoded forms, the
     * headers named by config('waf.inspect_headers'), and every input key and
     * scalar value (uploaded files contribute their client-supplied filename).
     */
    protected function inspectionSubject(Request $request): string
    {
        $uri = $request->getRequestUri();

        return implode(' ', [
            $uri,
            $this->fullyDecode($uri),
            $this->headerValues($request),
            $this->inputValues($request),
        ]);
    }

    /**
     * Percent-decode a URI far enough to defeat double-encoded payloads.
     *
     * getRequestUri() hands back the raw request line, so a traversal sent as
     * "%2e%2e%2f" — or "%252e%252e%252f" — never matches a pattern written
     * against literal "../" unless it is decoded first.
     */
    protected function fullyDecode(string $uri): string
    {
        $decoded = rawurldecode($uri);

        return $decoded.' '.rawurldecode($decoded);
    }

    /**
     * Collect the header values the WAF inspects.
     *
     * Deliberately an allow-list rather than every header: cookies and
     * framework-issued tokens carry opaque payloads that would only invite
     * false positives, while User-Agent and Referer are the headers attacks
     * are actually smuggled through.
     */
    protected function headerValues(Request $request): string
    {
        /** @var array<int, string> $headers */
        $headers = config('waf.inspect_headers', []);

        $values = [];

        foreach ($headers as $header) {
            $values[] = (string) $request->headers->get($header, '');
        }

        return implode(' ', $values);
    }

    /**
     * Flatten every input key and scalar value into one inspectable string.
     *
     * This used to be json_encode($request->all()), which returns false the
     * moment any value contains a byte that is not valid UTF-8 — the old
     * "?: ''" fallback then dropped the whole body from the subject, so a
     * single stray byte disabled every body and query check on the request.
     *
     * The values of config('waf.skip_input_keys') are left out (their key names
     * are not): a password is the one field a user is meant to fill with
     * unpredictable symbols, and scanning it turned a legitimate credential
     * containing a backtick into a bare 403. Every other field on those same
     * requests is still inspected.
     */
    protected function inputValues(Request $request): string
    {
        $skippedKeys = $this->skippedInputKeys();

        $parts = [];

        foreach (Arr::dot($request->all()) as $key => $value) {
            $parts[] = (string) $key;

            if (in_array(Str::lower(Str::afterLast((string) $key, '.')), $skippedKeys, true)) {
                continue;
            }

            if (is_scalar($value)) {
                $parts[] = (string) $value;

                continue;
            }

            if ($value instanceof UploadedFile) {
                $parts[] = (string) $value->getClientOriginalName();
            }
        }

        return implode(' ', $parts);
    }

    /**
     * The input keys whose values are exempt from the signature checks.
     *
     * Lower-cased so config entries stay case-insensitive, matching how
     * inputValues() compares the last segment of each dotted input key.
     *
     * @return list<string>
     */
    protected function skippedInputKeys(): array
    {
        /** @var array<int, string> $keys */
        $keys = config('waf.skip_input_keys', []);

        return array_values(array_map(
            fn (string $key): string => Str::lower($key),
            $keys,
        ));
    }

    /**
     * Log the offending request and abort with a 403.
     */
    protected function blockRequest(Request $request, string $category, string $reason): void
    {
        Log::warning('Blocked malicious request', [
            'ip' => $request->ip(),
            'category' => $category,
            'reason' => $reason,
            'uri' => $request->getRequestUri(),
        ]);

        abort(403);
    }
}
