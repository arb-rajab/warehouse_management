#!/usr/bin/env bash
#
# Stops this sandbox re-running a package install after one has already failed
# with a network/auth error. See the "Sandbox" section of CLAUDE.md for why an
# install cannot succeed here at all.
#
# Modes:
#   check  - PreToolUse: deny the attempt if an earlier one failed.
#   record - PostToolUse/PostToolUseFailure: remember that it failed.
#
# The failure text is not always visible to `record`: a backgrounded Bash call
# returns only "Command running in background with ID: ...", and the install's
# real output goes to a task file instead. So `record` also notes where that
# output will land, and `check` reads it before allowing the next attempt.

set -uo pipefail

MODE="${1:-}"
STATE_DIR="/tmp/claude-install-retry-guard"

INPUT="$(cat)"

INSTALL_RE='composer (install|update)|npm (install|ci)|yarn install|pnpm install'
NETFAIL_RE='could not authenticate against github|curl error [0-9]+|proxy CONNECT aborted|connection reset by peer|could not resolve host|operation timed out|failed to download|407 Proxy Authentication Required|ECONNRESET|ETIMEDOUT'

REASON='A package install already failed in this session with a network/auth error, and it cannot succeed in this sandbox: phpstan/phpstan is dist-only and its download host is 403 through the egress proxy. Do not retry composer/npm/yarn/pnpm install, and do not route around the block by seeding caches or rewriting package sources - a 403 from the proxy is an egress-policy denial to report, not to work around. Write the tests, push, and read Pint/PHPStan/Pest results from CI instead, or ask the user how to proceed.'

# In a Claude Code web/remote session, this container's own SessionStart hook
# (.claude/hooks/session-start.sh) already measured — not guessed — that every
# require-dev package 403s here, and says so up front in the session's own
# context ("Pint, Pest, PHPStan and Larastan cannot be installed in this
# sandbox ... don't push to find out"). That makes a first `composer
# install`/`update` pulling in require-dev a predictable multi-minute failure,
# not something worth confirming empirically once. Deny it outright — the
# retry-after-failure logic below still exists for npm/yarn/pnpm and for local
# (non-remote) sessions, where no such upfront measurement was made.
PREDICTABLE_DEV_INSTALL_REASON='This container'"'"'s SessionStart hook already told this session that Pint/Pest/PHPStan/Larastan cannot be installed here (a measured, permanent fact of this sandbox, not a per-session flake) - see the "Sandbox" section of CLAUDE.md. Re-running composer install/update without --no-dev to check anyway just repeats a multi-minute failure the session was already told about. Use `composer install --no-dev` (app-only, works fine) if you need a bootable app, and push + read Pint/PHPStan/Pest results from CI for anything dev-tooling-related.'

CMD="$(printf '%s' "$INPUT" | jq -r '.tool_input.command // empty')"

printf '%s' "$CMD" | grep -Eqi "$INSTALL_RE" || exit 0

SID="$(printf '%s' "$INPUT" | jq -r '.session_id // "unknown"')"
BLOCKED="$STATE_DIR/${SID}.blocked"
PENDING="$STATE_DIR/${SID}.pending"

deny() {
    jq -n --arg reason "$REASON" \
        '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:"deny",permissionDecisionReason:$reason}}'
    exit 0
}

deny_predictable_dev_install() {
    jq -n --arg reason "$PREDICTABLE_DEV_INSTALL_REASON" \
        '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:"deny",permissionDecisionReason:$reason}}'
    exit 0
}

block() {
    mkdir -p "$STATE_DIR"
    printf '%s\n' "$CMD" > "$BLOCKED"
}

case "$MODE" in
    check)
        [ -f "$BLOCKED" ] && deny

        if [ "${CLAUDE_CODE_REMOTE:-}" = "true" ] \
            && printf '%s' "$CMD" | grep -Eqi '^[[:space:]]*composer[[:space:]]+(install|update)\b' \
            && ! printf '%s' "$CMD" | grep -Eq -- '--no-dev\b'; then
            deny_predictable_dev_install
        fi

        # An earlier attempt may have been backgrounded; its failure only shows
        # up in the task output file, which never reached `record`.
        if [ -f "$PENDING" ]; then
            while IFS= read -r output_file; do
                [ -n "$output_file" ] && [ -f "$output_file" ] || continue

                if grep -Eqi "$NETFAIL_RE" "$output_file"; then
                    block
                    deny
                fi
            done < "$PENDING"
        fi
        ;;
    record)
        if printf '%s' "$INPUT" | grep -Eqi "$NETFAIL_RE"; then
            block
            exit 0
        fi

        mkdir -p "$STATE_DIR"
        printf '%s' "$INPUT" \
            | grep -oE '/[^ "]+/tasks/[A-Za-z0-9_-]+\.output' \
            | head -1 >> "$PENDING"
        ;;
esac

exit 0
