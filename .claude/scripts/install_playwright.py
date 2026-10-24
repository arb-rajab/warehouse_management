"""Prepare Chromium and video recording for Linux sandbox browser tests.

Run after npm ci: python3 .claude/scripts/install_playwright.py
Reuse an explicit browser, /opt/pw-browsers/chromium, a system browser, or
Playwright's cached browser, in that order. Download only missing binaries.
System FFmpeg is linked into Playwright's revision-specific cache when needed.
The default cache and generated environment file stay inside node_modules.

For direct browser runs, source node_modules/.playwright-runtime.env first.
pre_push_check.py discovers the same paths automatically. This helper is also
called by the existing SessionStart hook; Codex does not run Claude hooks itself.
Downloads use the inherited proxy/CA settings and fail normally if blocked.
"""

import json
import os
import shlex
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SHIPPED_BROWSERS = Path('/opt/pw-browsers')


def executable(path: str | Path | None) -> Path | None:
    candidate = Path(path) if path else None
    return candidate if candidate and candidate.is_file() and os.access(candidate, os.X_OK) else None


def runtime_paths(root: Path, env: dict[str, str]) -> tuple[Path, Path]:
    """Resolve the installed Playwright version's browser and FFmpeg paths."""
    source = '''
const { chromium } = require('@playwright/test');
const { browsers } = require('./node_modules/playwright-core/browsers.json');
console.log(JSON.stringify({ chromium: chromium.executablePath(),
    ffmpeg: browsers.find(browser => browser.name === 'ffmpeg').revision }));
'''
    result = subprocess.run(['node', '-e', source], cwd=root, env=env, check=True, capture_output=True, text=True)
    paths = json.loads(result.stdout)
    cache = root / 'node_modules/playwright-core/.local-browsers' if env['PLAYWRIGHT_BROWSERS_PATH'] == '0' else Path(env['PLAYWRIGHT_BROWSERS_PATH'])
    if not cache.is_absolute():
        cache = root / cache

    return Path(paths['chromium']), cache / f"ffmpeg-{paths['ffmpeg']}" / 'ffmpeg-linux'


def browser_environment(root: Path = ROOT, env: dict[str, str] | None = None) -> dict[str, str]:
    """Discover paths without installing anything; shared with the E2E runner."""
    environment = dict(os.environ if env is None else env)
    if sys.platform != 'linux':
        return environment

    environment['PLAYWRIGHT_BROWSERS_PATH'] = environment.get('PLAYWRIGHT_BROWSERS_PATH') or str(root / 'node_modules/.cache/ms-playwright')
    bundled, _ = runtime_paths(root, environment)
    candidates = [environment.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'), str(SHIPPED_BROWSERS / 'chromium')]
    candidates += [shutil.which(name, path=environment.get('PATH')) for name in ('chromium', 'chromium-browser', 'google-chrome')]
    candidates.append(str(bundled))
    browser = next((found for candidate in candidates if (found := executable(candidate))), None)
    if browser:
        environment['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'] = str(browser)
    else:
        environment.pop('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH', None)

    return environment


def ensure_installed(root: Path = ROOT, env: dict[str, str] | None = None) -> dict[str, str]:
    if sys.platform != 'linux':
        raise RuntimeError('This sandbox installer supports Linux; use playwright install on other platforms.')

    environment = browser_environment(root, env)
    bundled, ffmpeg = runtime_paths(root, environment)
    browser = executable(environment.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'))
    if browser:
        print(f'Chromium already installed: {browser}')
    else:
        print('Chromium missing; installing the Playwright-pinned browser.', flush=True)
        subprocess.run(['npx', '--no-install', 'playwright', 'install', 'chromium'], cwd=root, env=environment, check=True)
        browser = executable(bundled)
        if not browser:
            raise RuntimeError('Chromium installation produced no executable.')

    if executable(ffmpeg):
        print(f'Playwright FFmpeg already installed: {ffmpeg}')
    else:
        system = executable(shutil.which('ffmpeg', path=environment.get('PATH')))
        shipped = executable(SHIPPED_BROWSERS / ffmpeg.parent.name / ffmpeg.name)
        if existing := system or shipped:
            ffmpeg.parent.mkdir(parents=True, exist_ok=True)
            ffmpeg.unlink(missing_ok=True)
            ffmpeg.symlink_to(existing)
            print(f'Using existing FFmpeg: {existing}')
        else:
            print('FFmpeg missing; installing Playwright video support.', flush=True)
            subprocess.run(['npx', '--no-install', 'playwright', 'install', 'ffmpeg'], cwd=root, env=environment, check=True)

    if not executable(ffmpeg):
        raise RuntimeError('FFmpeg installation produced no executable.')

    environment['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'] = str(browser)
    env_file = root / 'node_modules/.playwright-runtime.env'
    env_file.write_text(''.join(f'export {key}={shlex.quote(environment[key])}\n' for key in (
        'PLAYWRIGHT_BROWSERS_PATH', 'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH',
    )))
    print(f'Playwright ready. For direct runs: source {env_file.relative_to(root)}')

    return environment


if __name__ == '__main__':
    try:
        ensure_installed()
    except (OSError, ValueError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f'Playwright setup failed: {error}', file=sys.stderr)
        if isinstance(error, subprocess.CalledProcessError) and error.stderr:
            print(error.stderr, file=sys.stderr)
        sys.exit(1)
