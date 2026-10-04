#!/usr/bin/env bash
#
# Tells the session what the asynchronous setup hook (session-start.sh) is doing.
# Runs synchronously and prints in well under a second: SessionStart stdout
# becomes session context, which the async hook's own output never does.
#
# It reads the state the setup hook records under CLAUDE_SETUP_STATE_DIR, so a
# resumed container whose setup already finished gets the result instead of a
# "running" notice. The race with a setup that has not written its state yet is
# harmless: no state file means it is just starting.

set -uo pipefail

# Local checkouts manage their own dependencies.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

stateDir="${CLAUDE_SETUP_STATE_DIR:-/tmp/claude-session-setup}"
state="$(cat "$stateDir/state" 2>/dev/null || true)"

case "$state" in
    done)
        cat "$stateDir/summary.txt" 2>/dev/null
        ;;
    failed)
        echo "Environment setup FAILED earlier in this container: run \`python3 .claude/scripts/wait_for_setup.py\` to see what is left, and read Pint/PHPStan/Pest from CI."
        ;;
    *)
        cat <<NOTICE
Environment setup (PHP 8.5, composer dev tools, .env, Vite build) is running in the background and takes about 10 minutes on a fresh container. Work that does not need it can start now. Before using composer, artisan, Pint, PHPStan, Pest, the Wayfinder files or the built assets, run \`python3 .claude/scripts/wait_for_setup.py\` once (in the background if the shell tool caps foreground commands, then wait for its completion notice instead of polling): it exits 0 when setup is done and prints its summary, 1 if setup failed or was interrupted.
NOTICE
        ;;
esac

exit 0
