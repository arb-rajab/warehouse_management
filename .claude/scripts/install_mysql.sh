#!/usr/bin/env bash
#
# Installs MySQL from Ubuntu's archive, starts it, and prepares the database
# the CI "Pest (MySQL migrations)" job uses, so .claude/scripts/pre_push_check.py
# can run those tests locally.
#
# Idempotent: reuses an answering prepared server, or makes sure an installed
# server is running and configured. Meant for Debian/Ubuntu containers,
# where it runs as root from .claude/hooks/session-start.sh; on any other
# machine install MySQL your own way.
#
#   bash .claude/scripts/install_mysql.sh           install, start and configure
#   bash .claude/scripts/install_mysql.sh --check   exit 0 when MySQL answers with
#                                                   the credentials below; changes
#                                                   nothing
#
# Measured in the web sandbox: the proxy answers HTTPS to the Ubuntu archive but
# not the plain-HTTP mirrors apt is configured with ("Could not resolve"), so the
# sources are switched to https:// first. The server is Ubuntu's MySQL 8.0, CI
# uses mysql:8.4; the migration tests it runs behave the same on both. Credentials
# match .github/workflows/tests.yml (root / password, database warehouse_test).
# The container resets the process list between sessions, so the server must be
# started again each time, which is why this runs on every SessionStart.

set -euo pipefail

DB_NAME='warehouse_test'
DB_ROOT_PASSWORD='password'

# `mysqladmin ping` exits 0 whenever a server is alive, even when it refuses the
# login, so credentials are checked with a real query.
alive() {
    mysqladmin ping >/dev/null 2>&1 || mysqladmin -uroot "-p${DB_ROOT_PASSWORD}" -h127.0.0.1 ping >/dev/null 2>&1
}

answers() {
    mysql -uroot "-p${DB_ROOT_PASSWORD}" -h127.0.0.1 -e 'SELECT 1' >/dev/null 2>&1
}

ensure_database() {
    mysql -uroot "-p${DB_ROOT_PASSWORD}" -h127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};" 2>/dev/null
}

if [ "${1:-}" = '--check' ]; then
    answers
    exit $?
fi

# Prepared environments may provide a remote or containerized server without
# mysqld or service management on this machine. Reuse it when CI's login works.
if answers; then
    ensure_database
    exit 0
fi

export DEBIAN_FRONTEND=noninteractive

apt_proxy=()
if [ -n "${HTTPS_PROXY:-}" ]; then
    apt_proxy=(-o "Acquire::https::Proxy=${HTTPS_PROXY}")
fi

if ! command -v mysqld >/dev/null 2>&1; then
    if [ -f /etc/apt/sources.list.d/ubuntu.sources ]; then
        sed -i 's#http://archive.ubuntu.com#https://archive.ubuntu.com#; s#http://security.ubuntu.com#https://security.ubuntu.com#' \
            /etc/apt/sources.list.d/ubuntu.sources
    fi

    apt-get update -qq "${apt_proxy[@]}" || true
    apt-get install -y -qq "${apt_proxy[@]}" mysql-server
fi

if ! alive; then
    service mysql start >/dev/null 2>&1 || true

    for _ in $(seq 1 30); do
        alive && break
        sleep 1
    done
fi

# A fresh install lets root in over the unix socket with no password; once the
# password below is set, TCP logins with it are what CI does.
if ! answers; then
    mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PASSWORD}'; FLUSH PRIVILEGES;"
fi

ensure_database

answers
