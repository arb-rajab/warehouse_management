#!/usr/bin/env bash
#
# Installs PHP 8.5 and every extension this app needs, from Ondřej Surý's PPA.
# composer.json requires ^8.5, which Ubuntu's own archive does not ship.
#
# Idempotent: when PHP >= 8.5 and every required module is already loaded it
# changes nothing. Meant for Debian/Ubuntu containers (Claude Code web
# sessions), where it runs as root from .claude/hooks/session-start.sh; on any
# other machine install PHP your own way.
#
#   bash .claude/scripts/install_php.sh           install what is missing
#   bash .claude/scripts/install_php.sh --check   exit 0 when satisfied, 1 when
#                                                 not; installs nothing
#
# The PPA signing key is pinned by full fingerprint (read from the keyserver and
# cross-checked against the PPA's own Launchpad page), so a key the keyserver
# serves under that ID but with different contents is refused.

set -euo pipefail

PPA_KEY_ID='71DAEAAB4AD4CAB6'
PPA_KEY_FINGERPRINT='B8DC7E53946656EFBCE4C1DD71DAEAAB4AD4CAB6'

# apt packages. gd is not optional: composer.lock requires ext-gd (mpdf,
# phpspreadsheet, simple-qrcode), and without it the dependency install fails
# its platform check.
PACKAGES=(
    php8.5-cli php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip
    php8.5-sqlite3 php8.5-mysql php8.5-bcmath php8.5-intl php8.5-gd
)

# What `php -m` must list for the packages above to be considered installed.
REQUIRED_MODULES=(mbstring dom curl zip pdo_sqlite pdo_mysql bcmath intl gd)

is_satisfied() {
    command -v php >/dev/null 2>&1 || return 1
    php -r 'exit(PHP_VERSION_ID >= 80500 ? 0 : 1);' || return 1

    local modules
    modules="$(php -m 2>/dev/null | tr 'A-Z' 'a-z')"

    local module
    for module in "${REQUIRED_MODULES[@]}"; do
        printf '%s\n' "$modules" | grep -qx "$module" || return 1
    done
}

if [ "${1:-}" = '--check' ]; then
    is_satisfied
    exit $?
fi

if is_satisfied; then
    exit 0
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg

if [ ! -f /etc/apt/sources.list.d/ondrej-php.list ]; then
    key_file="$(mktemp)"
    trap 'rm -f "$key_file"' EXIT

    curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x${PPA_KEY_ID}" -o "$key_file"

    actual_fingerprint="$(gpg --show-keys --with-colons "$key_file" | awk -F: '$1 == "fpr" { print $10; exit }')"
    if [ "$actual_fingerprint" != "$PPA_KEY_FINGERPRINT" ]; then
        echo "Refusing the PPA key: fingerprint ${actual_fingerprint:-<none>} is not ${PPA_KEY_FINGERPRINT}." >&2
        exit 1
    fi

    mkdir -p /etc/apt/keyrings
    gpg --dearmor --yes -o /etc/apt/keyrings/ondrej-php.gpg "$key_file"

    . /etc/os-release
    echo "deb [signed-by=/etc/apt/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu ${VERSION_CODENAME} main" \
        > /etc/apt/sources.list.d/ondrej-php.list

    apt-get update -qq
fi

apt-get install -y -qq "${PACKAGES[@]}"
update-alternatives --set php /usr/bin/php8.5

is_satisfied
