#!/usr/bin/env bash
#
# Runs on the Hostinger host, fed to `bash -l -s` over SSH by deploy.yml.
#
# Deploys are atomic: everything is built into a fresh releases/<id> directory
# and the `current` symlink is flipped only once every step has succeeded, so a
# failure at any point leaves the live site serving the previous release
# untouched. The window where the database changes is bracketed by maintenance
# mode, so no request can write a row that a rollback would then discard.
#
# MySQL cannot roll back DDL -- CREATE TABLE and ALTER TABLE auto-commit, which
# is why a failed migration leaves half-created tables behind. The only true
# undo is restoring the snapshot taken moments earlier, which is what this does.
#
# Expects in the environment: DEPLOY_PATH, TARGET_BRANCH, TARGET_SHA,
# PHP_BINARY, KEEP_RELEASES.

set -euo pipefail

readonly REPO_DIR="$DEPLOY_PATH/repo"
readonly SHARED_DIR="$DEPLOY_PATH/shared"
readonly RELEASES_DIR="$DEPLOY_PATH/releases"
readonly CURRENT_LINK="$DEPLOY_PATH/current"

release_id="$(date -u +%Y%m%d%H%M%S)-${TARGET_SHA:0:8}"
readonly RELEASE_DIR="$RELEASES_DIR/$release_id"

# Rollback bookkeeping: each is flipped on only once the corresponding undo
# becomes necessary, so the exit trap never tries to reverse something that
# never happened.
maintenance_engaged=0
database_backup=''
migrations_attempted=0
mysql_defaults_file=''

# --------------------------------------------------------------------------
# Helpers
# --------------------------------------------------------------------------

log() {
    echo "==> $*"
}

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

# Artisan against whichever release is actually serving traffic. Before the
# symlink flip that is the previous release -- known-good, and the one whose
# maintenance file the web server is reading. Falling back to the new release
# covers the very first deploy, when `current` does not exist yet.
live_artisan() {
    local root="$RELEASE_DIR"

    if [ -L "$CURRENT_LINK" ]; then
        root="$CURRENT_LINK"
    fi

    (cd "$root" && "$PHP_BINARY" artisan "$@")
}

# Read one key out of the shared .env, tolerating surrounding quotes. The
# credentials are needed by mysqldump and mysql, which are plain binaries and
# cannot ask Laravel for them.
env_value() {
    sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" "$SHARED_DIR/.env" \
        | head -n 1 \
        | sed -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

# --------------------------------------------------------------------------
# Rollback
# --------------------------------------------------------------------------

restore_database() {
    log "Restoring the database from the pre-migration snapshot"

    # A plain restore is not enough: mysqldump emits DROP TABLE only for tables
    # that existed when it ran, so a table the failed migration created is not
    # in the dump and would survive -- which is exactly the state that breaks
    # every later migrate with "table already exists". Drop everything first.
    {
        echo 'SET FOREIGN_KEY_CHECKS=0;'
        mysql --defaults-extra-file="$mysql_defaults_file" "$db_database" -N -B -e 'SHOW TABLES;' \
            | sed 's/.*/DROP TABLE IF EXISTS `&`;/'
        echo 'SET FOREIGN_KEY_CHECKS=1;'
    } | mysql --defaults-extra-file="$mysql_defaults_file" "$db_database"

    mysql --defaults-extra-file="$mysql_defaults_file" "$db_database" < "$database_backup"

    log "Database restored to its state before this deploy"
}

on_exit() {
    local exit_code=$?
    trap - EXIT

    if [ "$exit_code" -ne 0 ]; then
        echo
        log "Deploy failed (exit $exit_code) -- rolling back"

        if [ "$migrations_attempted" -eq 1 ] && [ -n "$database_backup" ]; then
            # Deliberately not guarded by `set -e`: if the restore itself fails
            # the operator needs to see it and still get the site back up.
            restore_database || echo "ERROR: restore failed; dump kept at $database_backup" >&2
        fi

        if [ "$maintenance_engaged" -eq 1 ]; then
            live_artisan up || echo "ERROR: could not leave maintenance mode; run 'artisan up' by hand" >&2
        fi

        rm -rf "$RELEASE_DIR"

        if [ -L "$CURRENT_LINK" ]; then
            log "Live release is unchanged: $(readlink "$CURRENT_LINK")"
        else
            log "No release was ever published; nothing is being served yet"
        fi
    fi

    [ -n "$mysql_defaults_file" ] && rm -f "$mysql_defaults_file"

    exit "$exit_code"
}

trap on_exit EXIT

# --------------------------------------------------------------------------
# Preflight -- fail before touching anything live
# --------------------------------------------------------------------------

[ -d "$SHARED_DIR" ] || fail "$SHARED_DIR does not exist. Run the one-time layout setup first."
[ -f "$SHARED_DIR/.env" ] || fail "$SHARED_DIR/.env is missing; every release symlinks to it."
[ -d "$REPO_DIR/.git" ] || fail "$REPO_DIR is not a git checkout. Run the one-time layout setup first."

command -v mysqldump >/dev/null || fail "mysqldump not found; it is required to snapshot the database before migrating."
command -v mysql >/dev/null || fail "mysql client not found; it is required to restore a failed migration."

db_database="$(env_value DB_DATABASE)"
db_username="$(env_value DB_USERNAME)"
db_password="$(env_value DB_PASSWORD)"
db_host="$(env_value DB_HOST)"

[ -n "$db_database" ] || fail "DB_DATABASE is not set in $SHARED_DIR/.env"

# Credentials go in a defaults file rather than on the command line, where they
# would be visible to anyone running `ps` on a shared host.
mysql_defaults_file="$(mktemp)"
chmod 600 "$mysql_defaults_file"
# No `database=` here: mysqldump reads [client] too and maps that key to
# --databases, which collides with the database named positionally and makes it
# warn and ignore the option. Every call below names the database explicitly.
cat > "$mysql_defaults_file" <<CNF
[client]
host=${db_host:-localhost}
user=$db_username
password="$db_password"
CNF

mysql --defaults-extra-file="$mysql_defaults_file" "$db_database" -e 'SELECT 1;' >/dev/null \
    || fail "Cannot connect to MySQL with the credentials in $SHARED_DIR/.env"

# --------------------------------------------------------------------------
# Build the new release (the live site is untouched throughout this section)
# --------------------------------------------------------------------------

log "Fetching $TARGET_BRANCH"
git -C "$REPO_DIR" fetch --prune origin "$TARGET_BRANCH"

log "Preparing release $release_id"
mkdir -p "$RELEASE_DIR"
git -C "$REPO_DIR" archive "$TARGET_SHA" | tar -x -C "$RELEASE_DIR"

log "Linking shared state into the release"
rm -rf "$RELEASE_DIR/storage"
ln -s "$SHARED_DIR/storage" "$RELEASE_DIR/storage"
ln -s "$SHARED_DIR/.env" "$RELEASE_DIR/.env"

# `git archive` omits empty directories, so do not assume the release carries
# one just because the repository does.
mkdir -p "$SHARED_DIR/database" "$RELEASE_DIR/database"
for sqlite_name in database telescope pulse health; do
    sqlite_file="$SHARED_DIR/database/$sqlite_name.sqlite"
    [ -f "$sqlite_file" ] || touch "$sqlite_file"
    ln -sf "$sqlite_file" "$RELEASE_DIR/database/$sqlite_name.sqlite"
done

# Entries the host needs inside the served directory but which are not part of
# the repository. On this account hPanel will only resolve a subdomain's
# document root under public_html, so the dev and staging roots have to sit
# inside production's public/ -- and that directory now comes fresh from
# `git archive` on every deploy, which would drop them. Keeping the originals
# in shared/ and relinking them here makes them survive.
#
# Targets in shared/public must be absolute: a relative one would resolve
# against the link's own directory, which differs between shared/ and a release.
if [ -d "$SHARED_DIR/public" ]; then
    log "Linking shared public entries into the release"
    for shared_entry in "$SHARED_DIR/public/"*; do
        # -L as well as -e so an entry pointing at an environment that has not
        # deployed yet is still linked, rather than skipped for being dangling.
        { [ -e "$shared_entry" ] || [ -L "$shared_entry" ]; } || continue
        ln -sfn "$shared_entry" "$RELEASE_DIR/public/$(basename "$shared_entry")"
    done
fi

previous_release=''
if [ -L "$CURRENT_LINK" ]; then
    previous_release="$(readlink -f "$CURRENT_LINK")"
fi

# Reinstalling ~110 composer and ~500 npm packages costs minutes and changes
# nothing when neither lockfile moved, so carry the previous release's trees
# over instead. Hardlinks make that near-free on disk; both tools replace files
# rather than editing in place, so the copies never diverge.
reuse_dependencies=0
if [ -n "$previous_release" ] \
    && cmp -s "$RELEASE_DIR/composer.lock" "$previous_release/composer.lock" \
    && cmp -s "$RELEASE_DIR/package-lock.json" "$previous_release/package-lock.json"; then
    reuse_dependencies=1
fi

if [ "$reuse_dependencies" -eq 1 ]; then
    log "Lockfiles unchanged; reusing the previous release's dependencies"
    for tree in vendor node_modules; do
        if [ -d "$previous_release/$tree" ]; then
            cp -al "$previous_release/$tree" "$RELEASE_DIR/$tree" 2>/dev/null \
                || cp -a "$previous_release/$tree" "$RELEASE_DIR/$tree"
        fi
    done
else
    log "Installing dependencies"

    composer_bin="$(command -v composer)"

    # Composer's shebang resolves to the account's default PHP, which is not
    # necessarily the one this app is pinned to. Drive it through $PHP_BINARY
    # when composer is a PHP script; CloudLinux hosts ship a shell wrapper that
    # picks the PHP itself, and handing that to `php` would fail outright.
    if head -n 1 "$composer_bin" | grep -qE '^#!.*[ /]php'; then
        (cd "$RELEASE_DIR" && "$PHP_BINARY" "$composer_bin" install \
            --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts)
    else
        (cd "$RELEASE_DIR" && "$composer_bin" install \
            --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts)
    fi

    # --no-scripts above skipped composer's post-autoload-dump hook, which runs
    # `artisan package:discover` through Symfony Process and therefore needs
    # proc_open -- disabled on this host. Do its work directly instead: bash
    # launching PHP needs no proc_open.
    rm -f "$RELEASE_DIR/bootstrap/cache/packages.php" "$RELEASE_DIR/bootstrap/cache/services.php"
    (cd "$RELEASE_DIR" && "$PHP_BINARY" artisan package:discover)

    (cd "$RELEASE_DIR" && npm ci)
fi

log "Building frontend assets"
(cd "$RELEASE_DIR" && npm run build)

# --------------------------------------------------------------------------
# The live window -- everything below is covered by maintenance mode
# --------------------------------------------------------------------------

log "Entering maintenance mode"
live_artisan down --retry=30
maintenance_engaged=1

log "Snapshotting the database"
mkdir -p "$SHARED_DIR/backups"
database_backup="$SHARED_DIR/backups/$release_id.sql"
mysqldump --defaults-extra-file="$mysql_defaults_file" \
    --single-transaction --routines --triggers --add-drop-table \
    "$db_database" > "$database_backup"

# An empty or truncated dump is worse than none: the trap would "restore" it
# over a working database. mysqldump writes this marker only on success.
tail -n 5 "$database_backup" | grep -q 'Dump completed' \
    || fail "mysqldump did not complete; refusing to migrate without a usable snapshot."

log "Running database migrations"
migrations_attempted=1
(cd "$RELEASE_DIR" && "$PHP_BINARY" artisan migrate --force)

log "Refreshing application caches"
(cd "$RELEASE_DIR" && "$PHP_BINARY" artisan config:cache)
(cd "$RELEASE_DIR" && "$PHP_BINARY" artisan route:cache)
(cd "$RELEASE_DIR" && "$PHP_BINARY" artisan view:cache)
# Not `artisan storage:link`: Filesystem::link() calls symlink(), and falls
# back to exec() when that is unavailable. This host disables both, so the
# command dies with "Call to undefined function Illuminate\Filesystem\exec()".
# The link it would create is the one pair in config/filesystems.php, and bash
# makes it without PHP's help. Absolute target, so it does not depend on
# resolving through the release's own storage symlink.
mkdir -p "$SHARED_DIR/storage/app/public"
ln -sfn "$SHARED_DIR/storage/app/public" "$RELEASE_DIR/public/storage"

# The publish step. Everything above can fail without consequence; past this
# line the new release is the live one. ln -sfn writes the new target onto a
# temporary name and renames it over the old one, which is atomic -- no request
# can observe a moment where `current` points at nothing.
log "Publishing release $release_id"
ln -sfn "$RELEASE_DIR" "$CURRENT_LINK.tmp"
mv -Tf "$CURRENT_LINK.tmp" "$CURRENT_LINK"

log "Leaving maintenance mode"
live_artisan up
maintenance_engaged=0

# Signals any worker started by cron to exit after its current job rather than
# continuing against code that has just been replaced. Hostinger Cloud has no
# supervisor, so the queue is drained by a cron `queue:work --stop-when-empty`.
log "Signalling queue workers to restart"
live_artisan queue:restart

log "Pruning old releases (keeping $KEEP_RELEASES)"
published="$(readlink -f "$CURRENT_LINK")"
# shellcheck disable=SC2012
ls -1 "$RELEASES_DIR" | sort -r | tail -n +"$((KEEP_RELEASES + 1))" | while read -r stale; do
    stale_path="$RELEASES_DIR/$stale"
    [ "$(readlink -f "$stale_path")" = "$published" ] && continue
    rm -rf "$stale_path"
done

# Backups are only useful for the deploy they belong to; the database's own
# backup schedule is a separate concern.
# shellcheck disable=SC2012
ls -1 "$SHARED_DIR/backups" | sort -r | tail -n +"$((KEEP_RELEASES + 1))" | while read -r stale; do
    rm -f "$SHARED_DIR/backups/$stale"
done

log "Deployed $TARGET_SHA as $release_id"
