"""Block until the background session setup has finished, then report how it went.

The SessionStart hook (.claude/hooks/session-start.sh) runs asynchronously: a
session starts at once while PHP, the dev tools and the Vite build are still
being provisioned, which takes one to two minutes on a fresh container. Anything
that needs them (composer, artisan, Pint, PHPStan, Pest, the Wayfinder files,
the built assets) must wait for it first:

    python3 .claude/scripts/wait_for_setup.py [timeout-seconds]

Run it once, in the background if your shell tool caps foreground commands, and
let its completion notice arrive rather than polling. It prints the hook's
summary and exits:

    0  setup finished, or none is in progress (a local checkout, a resumed
       container whose setup already finished)
    1  setup failed, was interrupted (the hook process is gone without
       finishing, e.g. killed by its timeout), or this wait timed out

The hook records its state under CLAUDE_SETUP_STATE_DIR (default
/tmp/claude-session-setup): `state` holds running, done or failed, `pid` the
hook's process id, and `summary.txt` the text it would otherwise have printed.
"""

import os
import sys
import time
from collections.abc import Callable
from pathlib import Path

DEFAULT_STATE_DIR = '/tmp/claude-session-setup'
DEFAULT_TIMEOUT_SECONDS = 1800
POLL_INTERVAL_SECONDS = 5


def state_dir() -> Path:
    """Where the hook records its state."""
    return Path(os.environ.get('CLAUDE_SETUP_STATE_DIR', DEFAULT_STATE_DIR))


def process_is_alive(pid: int) -> bool:
    """Whether a process with this id exists."""
    try:
        os.kill(pid, 0)
    except ProcessLookupError:
        return False
    except PermissionError:
        return True

    return True


def read_state(directory: Path) -> str:
    """Return absent, running, done, failed or interrupted for the recorded run.

    A `running` marker whose process is gone means the hook was killed before it
    could record an outcome, so it is reported as interrupted, not as running.
    """
    state_file = directory / 'state'
    if not state_file.exists():
        return 'absent'

    state = state_file.read_text().strip()
    if state != 'running':
        return state

    pid_file = directory / 'pid'
    pid_text = pid_file.read_text().strip() if pid_file.exists() else ''

    return 'running' if pid_text.isdigit() and process_is_alive(int(pid_text)) else 'interrupted'


def read_summary(directory: Path) -> str:
    """The hook's summary text, or an empty string when there is none."""
    summary = directory / 'summary.txt'

    return summary.read_text().strip() if summary.exists() else ''


def wait(
    directory: Path,
    timeout_seconds: float,
    sleep: Callable[[float], None] = time.sleep,
    clock: Callable[[], float] = time.monotonic,
) -> tuple[int, str]:
    """Wait for the run to leave `running`; return (exit code, message)."""
    deadline = clock() + timeout_seconds

    while True:
        state = read_state(directory)

        if state == 'absent':
            return 0, 'No session setup is in progress.'

        if state == 'done':
            return 0, read_summary(directory) or 'Session setup finished.'

        if state in ('failed', 'interrupted'):
            detail = 'was interrupted before it finished (killed or timed out)' if state == 'interrupted' else 'failed'
            summary = read_summary(directory)

            return 1, f'Session setup {detail}.' + (f'\n{summary}' if summary else '')

        if clock() >= deadline:
            return 1, f'Timed out after {int(timeout_seconds)}s: session setup is still running.'

        sleep(POLL_INTERVAL_SECONDS)


def main() -> int:
    timeout = float(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_TIMEOUT_SECONDS
    code, message = wait(state_dir(), timeout)
    print(message)

    return code


if __name__ == '__main__':
    sys.exit(main())
