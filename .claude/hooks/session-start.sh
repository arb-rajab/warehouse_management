#!/usr/bin/env bash
#
# Installs what this sandbox can actually install, so a web session can run
# checks locally instead of discovering every failure through CI.
#
# What is and isn't reachable here was measured, not assumed:
#
#   PHP 8.5 + extensions      ~15s   .claude/scripts/install_php.sh, from the ondrej
#                                    PPA, only when missing. composer.json needs
#                                    ^8.5 and composer.lock needs ext-gd; without
#                                    either the dependency install fails its
#                                    platform check and vendor/ never appears.
#   npm ci                    ~15s   vitest, vue-tsc, prettier, eslint
#   Chromium + FFmpeg         cached .claude/scripts/install_playwright.py reuses
#                                    installed binaries; downloads only missing
#                                    ones after npm ci completes.
#   composer dists            never  every locked package's dist URL
#                                    (api.github.com zipball) returns 403 through
#                                    the egress proxy, and so do codeload and
#                                    github.com/<repo>/archive.
#   composer, from git        ~35s   the proxy serves anonymous git fetches, so
#                                    .claude/scripts/install_dev_tools.py shallow-
#                                    fetches every locked commit in parallel and
#                                    installs them as local dists (its docstring
#                                    has the details). The --prefer-source install
#                                    it replaced took ~11m and ~7 GB.
#   .env + APP_KEY            ~1s    copied from .env.example only when .env is
#                                    absent, and done before the slow installs so
#                                    a timeout cannot skip it; without a key every
#                                    test that boots the app errors.
#   MySQL server              ~30s   .claude/scripts/install_mysql.sh, Ubuntu's mysql-server
#                                    over https mirrors (plain-http apt is not
#                                    reachable here), started on every session.
#   wayfinder + npm run build ~20s   public/build/manifest.json, which every
#                                    Inertia feature test needs: without it ~200
#                                    Pest tests fail with ViteManifestNotFound.
#                                    fonts.bunny.net is blocked here (403), which
#                                    fails the build, so it falls back to
#                                    .claude/scripts/build_without_fonts.py.
#
# MySQL and npm ci run in the background while composer installs: none of them
# shares a lock or a directory with another, and MySQL's apt install starts only
# after PHP's has finished. A container that already has node_modules for the
# current package-lock.json skips npm ci, and install_dev_tools.py fetches only
# what vendor/ lacks, so a warm run costs seconds. Each phase's duration is
# written to timings.txt in the state directory below.
#
# Neither failure is fatal: a session with no dependencies is exactly the
# situation this hook exists to improve on, never a reason to refuse to start.

set -uo pipefail

# Local checkouts manage their own dependencies.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0

# This hook runs asynchronously (.claude/settings.json): the session starts at
# once while the setup below runs. Record where it stands so a session can wait
# for it with .claude/scripts/wait_for_setup.py. The EXIT trap marks a run that
# dies for any other reason (a TERM from the hook's timeout included) as failed
# and stops the installs still running in the background; a SIGKILL skips it,
# which is why the waiter also checks that the recorded pid is still alive.
stateDir="${CLAUDE_SETUP_STATE_DIR:-/tmp/claude-session-setup}"
mkdir -p "$stateDir"

recordState() {
    printf '%s\n' "$1" > "$stateDir/state.tmp" && mv "$stateDir/state.tmp" "$stateDir/state"
}

stopBackgroundJobs() {
    local job
    for job in $(jobs -p); do
        pkill -TERM -P "$job" 2>/dev/null
        kill -TERM "$job" 2>/dev/null
    done
}

# Runs a command and appends how long it took to timings.txt.
timed() {
    local label="$1" started="$SECONDS" status
    shift
    "$@"
    status=$?
    printf '%-16s %4ss  exit %s\n' "$label" "$((SECONDS - started))" "$status" >> "$stateDir/timings.txt"

    return "$status"
}

rm -f "$stateDir/summary.txt" "$stateDir/timings.txt"
printf '%s\n' "$$" > "$stateDir/pid"
recordState running
trap '[ "$(cat "$stateDir/state" 2>/dev/null)" = running ] && recordState failed; stopBackgroundJobs' EXIT

# Everything below needs PHP 8.5 and its extensions, which this container may
# not have (and whose absence leaves vendor/ missing, so artisan, Wayfinder and
# the Vite build are all unavailable). A no-op when they are already present;
# failure is non-fatal, the install below then reports the platform error.
timed php bash .claude/scripts/install_php.sh >/dev/null 2>&1 || true

# A local MySQL server so pre_push_check.py can run the migration tests CI runs
# on MySQL. Installed once per container, but the process does not survive a
# resume, so this also starts it again. Non-fatal: without it that one stage is
# skipped and reported as such. In the background, and only now: its apt install
# must not overlap install_php.sh's.
timed mysql bash .claude/scripts/install_mysql.sh >/dev/null 2>&1 &
mysqlJob=$!

# A fresh container has no .env and phpunit.xml sets no APP_KEY, so every test
# that boots the app fails with "No application encryption key has been
# specified" (925 errors in one run) — an environment gap, not a code defect.
# Done here, before the slow installs, and without artisan (which needs vendor/):
# the dependency install can outlast the hook's timeout, and this must survive
# that. Only when .env is absent, so an existing key is never rotated.
if [ ! -f .env ] && cp .env.example .env; then
    appKey="$(php -r 'echo base64_encode(random_bytes(32));' 2>/dev/null)"
    if [ -n "$appKey" ]; then
        sed -i "s|^APP_KEY=.*|APP_KEY=base64:${appKey}|" .env
    fi
fi

frontend='unavailable'
backend='unavailable'
phpTools='cannot be installed in this sandbox (dev dists are 403 via the egress proxy) — read those from CI'
build='not built'
browser='unavailable'

# `npm ci` rather than `npm install`: install rewrites package-lock.json here
# (this container's npm strips `libc` fields from optional deps), which would
# leave every session with a dirty tree and invite a commit that breaks CI's
# own `npm ci`. It never mutates a tracked file. The lock's hash is stamped into
# node_modules afterwards, so a container whose node_modules already matches the
# lock skips it; any lock change, or a removed node_modules, runs it again.
npmStamp='node_modules/.claude-setup-lock-sha256'
npmLockHash="$(sha256sum package-lock.json 2>/dev/null | cut -d' ' -f1)"
if [ -n "$npmLockHash" ] && [ "$(cat "$npmStamp" 2>/dev/null)" = "$npmLockHash" ]; then
    npmJob=''
    frontend='ready'
else
    rm -f "$npmStamp"
    {
        timed 'npm ci' npm ci --no-audit --no-fund >/dev/null 2>&1 \
            && { [ -z "$npmLockHash" ] || printf '%s\n' "$npmLockHash" > "$npmStamp"; }
    } &
    npmJob=$!
fi

# The full install is a superset of --no-dev. If it fails, fall back to the
# app-only install so the session still boots.
if timed composer python3 .claude/scripts/install_dev_tools.py >/dev/null 2>&1; then
    backend='ready'
    phpTools='are installed: run composer lint:check and types:check; run Pest as COMPOSER_PROCESS_TIMEOUT=0 php artisan test --parallel, since composer test:pest hits composer'"'"'s 300s process timeout'
elif timed 'composer no-dev' composer install --no-dev --no-interaction --no-progress >/dev/null 2>&1; then
    backend='ready'
fi

if [ -n "$npmJob" ] && wait "$npmJob"; then
    frontend='ready'
fi

if [ "$frontend" = 'ready' ]; then
    if timed playwright python3 .claude/scripts/install_playwright.py >"$stateDir/playwright.log" 2>&1; then
        browser='ready (direct runs: source node_modules/.playwright-runtime.env)'
    else
        browser="unavailable (see ${stateDir}/playwright.log)"
    fi
fi

if [ "$backend" = 'ready' ]; then
    # Wayfinder's action/route files are gitignored and normally generated by
    # the vite plugin at build time. Without them eslint mis-sorts every
    # `@/actions/...` import and reports import/order errors that CI does not
    # have — phantom failures are worse than no linter at all.
    timed wayfinder php artisan wayfinder:generate >/dev/null 2>&1 || backend='ready (wayfinder generation failed; @/actions imports will mis-lint)'

    # Feature tests render the Inertia root view, which reads the Vite manifest.
    # The build needs the Wayfinder files above, and a missing manifest fails
    # ~200 tests for a reason that has nothing to do with the code under test.
    #
    # The build fetches Instrument Sans from fonts.bunny.net, which a sandbox
    # proxy may block (403). Then fall back to a font-less build from a temporary
    # config copy (vite.config.ts itself is untouched) rather than leave no manifest.
    if [ "$frontend" = 'ready' ]; then
        if timed build npm run build >/dev/null 2>&1; then
            build='built'
        elif timed 'build no-fonts' python3 .claude/scripts/build_without_fonts.py >/dev/null 2>&1; then
            build='built (without the remote font: fonts.bunny.net is unreachable)'
        fi
    fi
fi

mysqlState='unavailable'
if wait "$mysqlJob"; then
    mysqlState='ready'
fi

printf '%-16s %4ss\n' total "$SECONDS" >> "$stateDir/timings.txt"

# The two facts that change how the session works. Written to the state dir for
# wait_for_setup.py (an asynchronous hook's stdout never reaches the session) and
# printed for a manual run.
summary="Local tooling — frontend (vitest, vue-tsc, prettier, eslint): ${frontend}. App (artisan, migrations): ${backend}. Vite build (needed by Pest feature tests): ${build}.
Pint, Pest, PHPStan and Larastan ${phpTools}.
MySQL (migration tests, root/password, database warehouse_test): ${mysqlState}.
Playwright (Chromium and FFmpeg): ${browser}.
Setup took ${SECONDS}s (per phase: ${stateDir}/timings.txt)."

printf '%s\n' "$summary" > "$stateDir/summary.txt"
recordState done
printf '%s\n' "$summary"

exit 0
