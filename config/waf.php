<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Application Firewall
    |--------------------------------------------------------------------------
    |
    | Enforced by BlockMaliciousRequests on every request (web and API). Set
    | WAF_ENABLED=false if it ever produces a false positive that blocks
    | legitimate traffic, without having to deploy a code change.
    |
    */

    'enabled' => env('WAF_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | Paths (wildcards supported, matched against Request::is()) that skip
    | the signature checks below entirely.
    |
    */

    'exclude_paths' => [
        'telescope',
        'telescope/*',
        '_boost',
        '_boost/*',
        'up',
        '.well-known/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Inspected Headers
    |--------------------------------------------------------------------------
    |
    | Headers whose values are checked against the signatures below alongside
    | the URI and the request input. Kept to an allow-list on purpose: cookies
    | and framework-issued tokens carry opaque payloads that would only invite
    | false positives, while these are the headers attacks arrive in.
    |
    */

    'inspect_headers' => [
        'user-agent',
        'referer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Skipped Input Keys
    |--------------------------------------------------------------------------
    |
    | Input keys whose *values* are never matched against the signatures below
    | (the key names themselves still are). Credentials are the one class of
    | input a user is expected to fill with unpredictable symbols: the
    | production Password::defaults() in AppServiceProvider requires them, so a
    | password-manager value holding a backtick or "$(...)" matched the
    | command-injection signatures and produced a bare 403 before routing, with
    | no validation error the Inertia form could show. Excluding these three
    | keys is narrower than an exclude_paths entry for admin/login, admin/users
    | and password/change, which would switch the input checks off entirely on
    | the app's three most sensitive routes.
    |
    | Matched case-insensitively against the last segment of the dotted key, so
    | a nested "users.0.password" is covered too.
    |
    */

    'skip_input_keys' => [
        'password',
        'password_confirmation',
        'current_password',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attack Signatures
    |--------------------------------------------------------------------------
    |
    | Regex patterns checked against the request URI and all query/body input.
    | Grouped by category purely for clearer log messages when one matches.
    |
    */

    'patterns' => [
        'sql_injection' => [
            '/union\s+select/i',
            '/select\s+.*\s+from\s+information_schema/i',
            '/\bor\b\s+1\s*=\s*1/i',
            '/\bsleep\(\s*\d+\s*\)/i',
            '/benchmark\(/i',
            '/;\s*drop\s+table/i',
        ],

        'xss' => [
            '/<script\b/i',
            '/javascript:/i',
            '/on(error|load|click|mouseover)\s*=/i',
            '/<iframe\b/i',
        ],

        'path_traversal' => [
            '#\.\./#',
            '#\.\.\\\\#',
            '/etc\/passwd/i',
        ],

        'command_injection' => [
            '/\$\([^)]*\)/',
            '/`[^`]*`/',
            '/\bwget\s+http/i',
            '/\bcurl\s+http/i',
        ],
    ],

];
