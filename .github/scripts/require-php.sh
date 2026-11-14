#!/usr/bin/env bash
#
# Checks the PHP a self-hosted runner provides, instead of installing one.
#
# setup-php installs PHP machine-wide and warns against running several
# self-hosted runners on one machine, because their parallel jobs conflict over
# that install. The VPS runs several, so it is provisioned with PHP once
# (docs/self-hosted-runner.md) and every job only verifies it here, failing with
# the exact gap rather than with an obscure error later in the job.
#
# Usage: bash .github/scripts/require-php.sh <major.minor>

set -euo pipefail

want="${1:?usage: require-php.sh <major.minor>}"
problems=()

if ! command -v php > /dev/null 2>&1; then
    echo "::error::PHP is not on PATH. This self-hosted runner must provide PHP $want (docs/self-hosted-runner.md)."
    exit 1
fi

have="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
[ "$have" = "$want" ] || problems+=("PHP $have is on PATH; the workflow needs PHP $want")

# What composer.lock requires of the platform, plus what the test suites
# connect with (SQLite for Pest and Playwright, MySQL for the migration tests)
# and intl, which Laravel's Number helper needs at run time.
# shellcheck disable=SC2016 # PHP code: its $ must not expand.
missing="$(php -r '
    $required = ["calendar", "ctype", "curl", "dom", "fileinfo", "filter", "gd", "hash", "iconv",
        "intl", "json", "libxml", "mbstring", "openssl", "pcre", "pdo_mysql", "pdo_sqlite", "phar",
        "reflection", "session", "simplexml", "sqlite3", "tokenizer", "xml", "xmlreader",
        "xmlwriter", "zip", "zlib"];
    echo implode(" ", array_filter($required, fn ($extension) => ! extension_loaded($extension)));
')"
[ -z "$missing" ] || problems+=("missing PHP extensions: $missing")

if ! command -v composer > /dev/null 2>&1; then
    problems+=("Composer is not on PATH")
elif ! composer --version --no-ansi 2> /dev/null | grep -q 'version 2\.'; then
    problems+=("Composer on PATH is not version 2")
fi

if [ "${#problems[@]}" -gt 0 ]; then
    for problem in "${problems[@]}"; do
        echo "::error::$problem (docs/self-hosted-runner.md)."
    done
    exit 1
fi

# setup-php runs without a coverage driver and without a memory limit; a
# runner that differs is slower or can fail where CI on GitHub would not.
php -r 'exit(extension_loaded("xdebug") ? 0 : 1);' \
    && echo '::warning::Xdebug is loaded; it slows every PHP job. Disable it on the runner.'
[ "$(php -r 'echo ini_get("memory_limit");')" = '-1' ] \
    || echo '::warning::memory_limit is not -1 (setup-php sets -1); Pest or PHPStan may run out of memory.'

echo "PHP $have with Composer 2 and the required extensions."
