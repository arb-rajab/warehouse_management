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
        'telescope*',
        '_boost*',
        'up',
        '.well-known/*',
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
