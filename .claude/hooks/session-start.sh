#!/usr/bin/env bash
#
# Installs what this sandbox can actually install, so a web session can run
# checks locally instead of discovering every failure through CI.
#
# What is and isn't reachable here was measured, not assumed:
#
#   PHP 8.5 + extensions      ~1m    .claude/scripts/install_php.sh, from the ondrej
#                                    PPA, only when missing. composer.json needs
#                                    ^8.5 and composer.lock needs ext-gd; without
#                                    either the dependency install fails its
#                                    platform check and vendor/ never appears.
#   npm ci                    ~15s   vitest, vue-tsc, prettier, eslint
#   composer install --no-dev ~4m    a bootable app: artisan, migrations, tinker
#   dev dependencies, dist    never  every dev package's dist URL
#                                    (api.github.com zipball) returns 403 through
#                                    the egress proxy: pint, pest, phpstan and
#                                    larastan alike.
#   dev dependencies, git     works  the proxy serves anonymous git clones, so
#                                    .claude/scripts/install_dev_tools.py installs
#                                    them with `--prefer-source` (its docstring
#                                    covers the phpstan special case).
#   .env + APP_KEY            ~1s    copied from .env.example only when .env is
#                                    absent, and done before the slow installs so
#                                    a timeout cannot skip it; without a key every
#                                    test that boots the app errors.
#   npm run build             ~1m    public/build/manifest.json, which every
#                                    Inertia feature test needs: without it ~200
#                                    Pest tests fail with ViteManifestNotFound.
#                                    fonts.bunny.net is blocked here (403), which
#                                    fails the build, so it falls back to
#                                    .claude/scripts/build_without_fonts.py.
#
# All of these are idempotent and the container is cached once the hook
# finishes, so the composer cost is paid on a cold container, not every session.
# A cold run measured ~10 minutes in total (the dev-tools install alone ~9m20s),
# which is why the hook's timeout in .claude/settings.json is 1800s, not 600s: at
# 600s the hook was killed before .env, Wayfinder and the Vite build ran.
# Neither failure is fatal: a session with no dependencies is exactly the
# situation this hook exists to improve on, never a reason to refuse to start.

set -uo pipefail

# Local checkouts manage their own dependencies.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0

# This hook runs asynchronously (.claude/settings.json): the session starts at
# once and the setup below takes ~10 minutes on a fresh container. Record where
# it stands so a session can wait for it with .claude/scripts/wait_for_setup.py.
# The EXIT trap marks a run that dies for any other reason (a TERM from the
# hook's timeout included) as failed; a SIGKILL skips it, which is why the
# waiter also checks that the recorded pid is still alive.
stateDir="${CLAUDE_SETUP_STATE_DIR:-/tmp/claude-session-setup}"
mkdir -p "$stateDir"

recordState() {
    printf '%s\n' "$1" > "$stateDir/state.tmp" && mv "$stateDir/state.tmp" "$stateDir/state"
}

rm -f "$stateDir/summary.txt"
printf '%s\n' "$$" > "$stateDir/pid"
recordState running
trap '[ "$(cat "$stateDir/state" 2>/dev/null)" = running ] && recordState failed' EXIT

# Everything below needs PHP 8.5 and its extensions, which this container may
# not have (and whose absence leaves vendor/ missing, so artisan, Wayfinder and
# the Vite build are all unavailable). A no-op when they are already present;
# failure is non-fatal, the install below then reports the platform error.
bash .claude/scripts/install_php.sh >/dev/null 2>&1 || true

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

# `npm ci` rather than `npm install`: install rewrites package-lock.json here
# (this container's npm strips `libc` fields from optional deps), which would
# leave every session with a dirty tree and invite a commit that breaks CI's
# own `npm ci`. Costs ~15s against install's ~3s warm, and never mutates a
# tracked file.
if npm ci --no-audit --no-fund >/dev/null 2>&1; then
    frontend='ready'
fi

# The full install comes first and is a superset of --no-dev. If it fails, fall
# back to the app-only install so the session still boots.
if python3 .claude/scripts/install_dev_tools.py >/dev/null 2>&1; then
    backend='ready'
    phpTools='are installed: run composer lint:check and types:check; run Pest as COMPOSER_PROCESS_TIMEOUT=0 php artisan test --parallel, since composer test:pest hits composer'"'"'s 300s process timeout'
elif composer install --no-dev --no-interaction --no-progress >/dev/null 2>&1; then
    backend='ready'
fi

if [ "$backend" = 'ready' ]; then
    # Wayfinder's action/route files are gitignored and normally generated by
    # the vite plugin at build time. Without them eslint mis-sorts every
    # `@/actions/...` import and reports import/order errors that CI does not
    # have — phantom failures are worse than no linter at all.
    php artisan wayfinder:generate >/dev/null 2>&1 || backend='ready (wayfinder generation failed; @/actions imports will mis-lint)'

    # Feature tests render the Inertia root view, which reads the Vite manifest.
    # The build needs the Wayfinder files above, and a missing manifest fails
    # ~200 tests for a reason that has nothing to do with the code under test.
    #
    # The build fetches Instrument Sans from fonts.bunny.net, which a sandbox
    # proxy may block (403). Then fall back to a font-less build from a temporary
    # config copy (vite.config.ts itself is untouched) rather than leave no manifest.
    if [ "$frontend" = 'ready' ]; then
        if npm run build >/dev/null 2>&1; then
            build='built'
        elif python3 .claude/scripts/build_without_fonts.py >/dev/null 2>&1; then
            build='built (without the remote font: fonts.bunny.net is unreachable)'
        fi
    fi
fi

# The two facts that change how the session works. Written to the state dir for
# wait_for_setup.py (an asynchronous hook's stdout never reaches the session) and
# printed for a manual run.
summary="Local tooling — frontend (vitest, vue-tsc, prettier, eslint): ${frontend}. App (artisan, migrations): ${backend}. Vite build (needed by Pest feature tests): ${build}.
Pint, Pest, PHPStan and Larastan ${phpTools}."

printf '%s\n' "$summary" > "$stateDir/summary.txt"
recordState done
printf '%s\n' "$summary"

exit 0
