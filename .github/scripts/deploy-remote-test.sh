#!/usr/bin/env bash
#
# Tests the rollback guarantees of deploy-remote.sh against stubbed binaries.
#
# That script restores the database by dropping every table and reloading a
# dump, so a regression in its failure handling destroys data on a live host.
# Nothing else exercises it: it only ever runs on Hostinger, over SSH, as part
# of a real deploy. These cases stand in for that.
#
# The stubs model a MySQL database as a file of table names, so a "migration"
# can append a table and *then* fail -- reproducing the one behaviour that makes
# rollback necessary at all, MySQL committing DDL that no transaction can undo.

set -euo pipefail

readonly SCRIPT_UNDER_TEST="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/deploy-remote.sh"
readonly WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

failures=0

pass() { echo "    ok   - $1"; }
fail() { echo "    FAIL - $1"; failures=$((failures + 1)); }

check() {
    if [ "$2" = "$3" ]; then pass "$1"; else fail "$1 (expected '$3', got '$2')"; fi
}

# --------------------------------------------------------------------------
# Fixture
# --------------------------------------------------------------------------

setup() {
    rm -rf "$WORK"/*
    mkdir -p "$WORK"/{bin,origin,host}

    git init -q -b develop "$WORK/origin"
    (
        cd "$WORK/origin"
        mkdir -p database public storage/framework bootstrap/cache
        echo '{"json":1}' > composer.json
        echo '{"lock":1}' > composer.lock
        echo '{"lock":1}' > package-lock.json
        echo '<?php' > public/index.php
        # Mirrors the real repository, where database/ carries migrations --
        # git archive would otherwise drop the empty directory entirely.
        echo '# migrations live here' > database/.gitkeep
        git add -A
        git -c user.email=t@t -c user.name=t commit -qm init
    )

    cat > "$WORK/bin/mysqldump" <<'STUB'
#!/bin/bash
echo "-- fake dump"; cat "$DBSTATE"; echo "-- Dump completed"
STUB

    cat > "$WORK/bin/mysql" <<'STUB'
#!/bin/bash
args="$*"
[[ "$args" == *"SELECT 1"* ]] && exit 0
[[ "$args" == *"SHOW TABLES"* ]] && { cat "$DBSTATE"; exit 0; }
input="$(cat)"
if [[ "$input" == *"Dump completed"* ]]; then
    grep -v '^--' <<<"$input" | grep -v '^$' > "$DBSTATE"
elif [[ "$input" == *"DROP TABLE"* ]]; then
    : > "$DBSTATE"
fi
exit 0
STUB

    # `migrate` appends a table before honouring FAIL_MIGRATE: the partial DDL
    # is the point, not an accident of the stub.
    # Every artisan command is appended to $ARTISAN_LOG, so a case can assert
    # which ran and in what order.
    cat > "$WORK/bin/php85" <<'STUB'
#!/bin/bash
if [ "$1" = '-r' ]; then
    exit "${FAIL_PHP_SECURITY_CHECK:-0}"
fi
if [ "$1" = "$(command -v composer)" ]; then
    echo "composer:$2" >> "$ARTISAN_LOG"
    exec bash "$@"
fi
echo "$2" >> "$ARTISAN_LOG"
case "$2" in
  migrate) echo "orphan_table" >> "$DBSTATE"
           [ "${FAIL_MIGRATE:-0}" = 1 ] && { echo "SQLSTATE[42S01] simulated" >&2; exit 1; } ;;
  event:cache) [ "${FAIL_EVENT_CACHE:-0}" = 1 ] && { echo "event cache error" >&2; exit 1; } ;;
  down) touch "$MAINT" ;;
  up)   rm -f "$MAINT" ;;
  queue:restart) [ "${FAIL_AFTER_PUBLISH:-0}" = 1 ] && { echo "simulated post-publish failure" >&2; exit 1; } ;;
esac
exit 0
STUB

    cat > "$WORK/bin/npm" <<'STUB'
#!/bin/bash
[ "$1 $2" = "run build" ] && [ "${FAIL_BUILD:-0}" = 1 ] && { echo "build error" >&2; exit 1; }
exit 0
STUB

    # Leaves one file per release, so a test can tell an install (a new file)
    # from a reused vendor/ (only the files carried over).
    cat > "$WORK/bin/composer" <<'STUB'
#!/bin/sh
echo "$*" >> "$COMPOSER_LOG"
case "$1" in
  audit) exit "${FAIL_AUDIT:-0}" ;;
  check-platform-reqs) exit "${FAIL_PLATFORM:-0}" ;;
  install) mkdir -p vendor && touch "vendor/installed-by-$(basename "$PWD")" ;;
  *) exit 99 ;;
esac
exit 0
STUB
    chmod +x "$WORK/bin"/*

    (
        cd "$WORK/host"
        mkdir -p shared/storage/framework shared/database releases
        git clone -q "$WORK/origin" repo
        printf 'DB_DATABASE=wms\nDB_USERNAME=u\nDB_PASSWORD="p@ss w0rd"\nDB_HOST=localhost\n' > shared/.env
    )
    printf 'users\nmigrations\n' > "$WORK/dbstate"
}

commit_new_revision() {
    (
        cd "$WORK/origin"
        echo "$RANDOM" > "change-$RANDOM.txt"
        git add -A
        git -c user.email=t@t -c user.name=t commit -qm change
        git rev-parse HEAD
    )
}

deploy() {
    env PATH="$WORK/bin:$PATH" \
        DBSTATE="$WORK/dbstate" MAINT="$WORK/maint" ARTISAN_LOG="$WORK/artisan.log" \
        COMPOSER_LOG="$WORK/composer.log" \
        DEPLOY_PATH="$WORK/host" TARGET_BRANCH=develop TARGET_SHA="$1" \
        PHP_BINARY=php85 KEEP_RELEASES=2 \
        "${@:2}" \
        bash "$SCRIPT_UNDER_TEST" >/dev/null 2>&1
}

published() { basename "$(readlink "$WORK/host/current")"; }
tables()    { tr '\n' ' ' < "$WORK/dbstate"; }
maintenance_state() { [ -f "$WORK/maint" ] && echo on || echo off; }

# --------------------------------------------------------------------------
# Cases
# --------------------------------------------------------------------------

echo "a first deploy publishes and leaves maintenance mode"
setup
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" && rc=0 || rc=$?
check "exits successfully" "$rc" "0"
check "current points at a release" "$([ -L "$WORK/host/current" ] && echo yes || echo no)" "yes"
check "site is live" "$(maintenance_state)" "off"
# storage:link cannot run on the target host, so the link is made in bash.
check "public/storage links into shared storage" \
    "$(readlink "$WORK/host/current/public/storage")" "$WORK/host/shared/storage/app/public"

# Production health fails OptimizedAppCheck until config, routes and events are
# all cached, so the deploy must run each of them, after migrate and before it
# publishes the release (queue:restart is the first command after the flip).
artisan_order() { grep -E '^(migrate|config:cache|event:cache|route:cache|view:cache|queue:restart)$' "$WORK/artisan.log" | tr '\n' ' '; }

echo "a deploy caches config, events, routes and views after migrating, before publishing"
check "caches run in order between migrate and queue:restart" \
    "$(artisan_order)" "migrate config:cache event:cache route:cache view:cache queue:restart "

echo "a failed event:cache rolls the deploy back like any other cache step"
before_tables="$(tables)"
before_release="$(published)"
deploy "$(commit_new_revision)" FAIL_EVENT_CACHE=1 && rc=0 || rc=$?
check "exits non-zero" "$rc" "1"
check "live release is unchanged" "$(published)" "$before_release"
check "database is back to its pre-deploy state" "$(tables)" "$before_tables"
check "route:cache ran only for the first deploy, not after the failure" \
    "$(grep -c '^route:cache$' "$WORK/artisan.log" | tr -d ' ')" "1"
check "site is out of maintenance" "$(maintenance_state)" "off"

echo "a failed migration rolls the database back and keeps the old release live"
before_tables="$(tables)"
before_release="$(published)"
deploy "$(commit_new_revision)" FAIL_MIGRATE=1 && rc=0 || rc=$?
check "exits non-zero" "$rc" "1"
check "live release is unchanged" "$(published)" "$before_release"
check "database is back to its pre-deploy state" "$(tables)" "$before_tables"
check "site is out of maintenance" "$(maintenance_state)" "off"
check "failed release directory is gone" "$(ls -1 "$WORK/host/releases" | wc -l | tr -d ' ')" "1"

echo "a failure before the live window never touches the database"
before_tables="$(tables)"
before_release="$(published)"
deploy "$(commit_new_revision)" FAIL_BUILD=1 && rc=0 || rc=$?
check "exits non-zero" "$rc" "1"
check "live release is unchanged" "$(published)" "$before_release"
check "database is untouched" "$(tables)" "$before_tables"
check "site never entered maintenance" "$(maintenance_state)" "off"

echo "successive deploys publish and prune to KEEP_RELEASES"
for _ in 1 2 3; do deploy "$(commit_new_revision)" || fail "deploy failed unexpectedly"; done
check "old releases are pruned" "$(ls -1 "$WORK/host/releases" | wc -l | tr -d ' ')" "2"
check "old backups are pruned" "$(ls -1 "$WORK/host/shared/backups" | wc -l | tr -d ' ')" "2"
check "current still resolves" "$([ -d "$WORK/host/current" ] && echo yes || echo no)" "yes"

# The release whose composer stub run produced the live vendor/: its own id after
# an install, an earlier release's after a reuse.
vendor_installed_by() { ls -1 "$WORK/host/current/vendor" | sed 's/^installed-by-//'; }

echo "vendor/ is reused only while composer.json and both lockfiles are unchanged"
setup
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" >/dev/null
first_release="$(published)"
check "the first deploy installs" "$(vendor_installed_by)" "$first_release"
deploy "$(commit_new_revision)" >/dev/null
check "an unrelated change reuses the previous vendor/" "$(vendor_installed_by)" "$first_release"
(
    cd "$WORK/origin"
    echo '{"json":2}' > composer.json
    git add -A
    git -c user.email=t@t -c user.name=t commit -qm "composer.json only"
)
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" >/dev/null
check "a composer.json change alone reinstalls" "$(vendor_installed_by)" "$(published)"
(
    cd "$WORK/origin"
    echo '{"lock":2}' > composer.lock
    git add -A
    git -c user.email=t@t -c user.name=t commit -qm "lock"
)
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" >/dev/null
check "a lockfile change reinstalls" "$(vendor_installed_by)" "$(published)"

echo "PHP security checks run for installs and reused dependencies"
check "every deploy audits production dependencies" \
    "$(grep -c '^audit --locked --no-dev --abandoned=report --no-interaction --no-plugins --no-scripts$' "$WORK/composer.log")" "4"
check "every deploy checks real production platform requirements" \
    "$(grep -c '^check-platform-reqs --no-dev --no-interaction --no-plugins --no-scripts$' "$WORK/composer.log")" "4"

for security_failure in 'FAIL_AUDIT=1' 'FAIL_AUDIT=100' 'FAIL_PLATFORM=1'; do
    for dependency_mode in reused fresh; do
        echo "$security_failure blocks a deploy with $dependency_mode dependencies before maintenance"
        setup
        deploy "$(git -C "$WORK/origin" rev-parse HEAD)"
        before_release="$(published)"
        before_tables="$(tables)"
        before_artisan="$(cat "$WORK/artisan.log")"
        if [ "$dependency_mode" = fresh ]; then
            echo '{"lock":2}' > "$WORK/origin/composer.lock"
        fi
        deploy "$(commit_new_revision)" "$security_failure" && rc=0 || rc=$?
        check "exits with the security check failure" "$rc" "${security_failure#*=}"
        check "live release is unchanged" "$(published)" "$before_release"
        check "database is untouched" "$(tables)" "$before_tables"
        check "site never entered maintenance" "$(maintenance_state)" "off"
        check "no backup was taken for the rejected release" \
            "$(ls -1 "$WORK/host/shared/backups" | wc -l | tr -d ' ')" "1"
        check "rejected release is removed" \
            "$(ls -1 "$WORK/host/releases" | wc -l | tr -d ' ')" "1"
        if [ "$security_failure" != 'FAIL_PLATFORM=1' ] || [ "$dependency_mode" = reused ]; then
            check "no application command ran" "$(cat "$WORK/artisan.log")" "$before_artisan"
        fi
    done
done

echo "an unpatched PHP engine blocks deployment before any live operation"
setup
deploy "$(git -C "$WORK/origin" rev-parse HEAD)"
before_release="$(published)"
before_tables="$(tables)"
before_artisan="$(cat "$WORK/artisan.log")"
before_composer="$(cat "$WORK/composer.log")"
deploy "$(commit_new_revision)" FAIL_PHP_SECURITY_CHECK=1 && rc=0 || rc=$?
check "exits non-zero" "$rc" "1"
check "live release is unchanged" "$(published)" "$before_release"
check "database is untouched" "$(tables)" "$before_tables"
check "site never entered maintenance" "$(maintenance_state)" "off"
check "no application command ran" "$(cat "$WORK/artisan.log")" "$before_artisan"
check "no dependency command ran" "$(cat "$WORK/composer.log")" "$before_composer"

echo "a PHP Composer script uses PHP_BINARY for security checks and installation"
setup
sed -i '1c#!/usr/bin/env php' "$WORK/bin/composer"
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" && rc=0 || rc=$?
check "exits successfully" "$rc" "0"
check "all Composer commands ran through the selected PHP" \
    "$(grep '^composer:' "$WORK/artisan.log" | tr '\n' ' ')" \
    "composer:audit composer:install composer:check-platform-reqs "

echo "an unavailable advisory service also blocks the first deploy safely"
setup
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" FAIL_AUDIT=100 && rc=0 || rc=$?
check "exits with the audit error" "$rc" "100"
check "no release was published" "$([ -L "$WORK/host/current" ] && echo yes || echo no)" "no"
check "database is untouched" "$(tables)" "users migrations "
check "site never entered maintenance" "$(maintenance_state)" "off"
check "no application command ran" "$([ -f "$WORK/artisan.log" ] && echo yes || echo no)" "no"
check "rejected release is removed" \
    "$(ls -1 "$WORK/host/releases" | wc -l | tr -d ' ')" "0"

echo "shared public entries are relinked into every release"
setup
mkdir -p "$WORK/host/shared/public" "$WORK/elsewhere"
ln -s "$WORK/elsewhere" "$WORK/host/shared/public/subdomain"
# An entry whose target does not exist yet, standing in for an environment
# that has not had its first deploy.
ln -s "$WORK/not-deployed-yet" "$WORK/host/shared/public/pending"
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" && rc=0 || rc=$?
check "exits successfully" "$rc" "0"
check "resolvable entry is linked" "$([ -L "$WORK/host/current/public/subdomain" ] && echo yes || echo no)" "yes"
check "dangling entry is linked anyway" "$([ -L "$WORK/host/current/public/pending" ] && echo yes || echo no)" "yes"
deploy "$(commit_new_revision)" >/dev/null
check "entries survive the next deploy" "$([ -L "$WORK/host/current/public/subdomain" ] && echo yes || echo no)" "yes"

echo "a failure after publishing leaves the release live and the database alone"
setup
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" >/dev/null
previous_release="$(published)"
tables_before="$(tables)"
# queue:restart runs after the symlink flip, so this fails once the new release
# is already serving traffic -- the case where rolling back does more harm than
# the failure itself.
deploy "$(commit_new_revision)" FAIL_AFTER_PUBLISH=1 && rc=0 || rc=$?
check "exits non-zero" "$rc" "1"
check "the new release is the published one" \
    "$([ "$(published)" != "$previous_release" ] && echo yes || echo no)" "yes"
check "the published release still exists on disk" \
    "$([ -d "$WORK/host/current" ] && echo yes || echo no)" "yes"
check "the database keeps the migration rather than being restored" \
    "$([ "$(tables)" != "$tables_before" ] && echo kept || echo restored)" "kept"
check "site is out of maintenance" "$(maintenance_state)" "off"

echo "a dangling current symlink from an earlier failure is repaired, not fatal"
setup
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" >/dev/null
# The state a failure leaves behind when it removes the release `current` points
# at. `current` is still a symlink, so a -L test would call it a live release
# and try to cd into nothing -- killing the very deploy that would repair it.
rm -rf "$(readlink "$WORK/host/current")"
check "current dangles before the deploy" \
    "$({ [ -L "$WORK/host/current" ] && [ ! -d "$WORK/host/current" ]; } && echo yes || echo no)" "yes"
deploy "$(commit_new_revision)" && rc=0 || rc=$?
check "exits successfully" "$rc" "0"
check "current resolves again" "$([ -d "$WORK/host/current" ] && echo yes || echo no)" "yes"
check "site is live" "$(maintenance_state)" "off"

echo "a missing shared storage tree is created rather than failing the deploy"
setup
rm -rf "$WORK/host/shared/storage"
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" && rc=0 || rc=$?
check "exits successfully" "$rc" "0"
check "framework/views exists" \
    "$([ -d "$WORK/host/shared/storage/framework/views" ] && echo yes || echo no)" "yes"
check "logs exists" "$([ -d "$WORK/host/shared/storage/logs" ] && echo yes || echo no)" "yes"

echo "a truncated dump aborts before migrating"
setup
deploy "$(git -C "$WORK/origin" rev-parse HEAD)" >/dev/null
before_tables="$(tables)"
printf '#!/bin/bash\necho "-- truncated"\n' > "$WORK/bin/mysqldump"
chmod +x "$WORK/bin/mysqldump"
deploy "$(commit_new_revision)" && rc=0 || rc=$?
check "exits non-zero" "$rc" "1"
check "migrations never ran" "$(tables)" "$before_tables"
check "site is out of maintenance" "$(maintenance_state)" "off"

echo
if [ "$failures" -eq 0 ]; then
    echo "All deploy rollback checks passed."
else
    echo "$failures check(s) failed."
    exit 1
fi
