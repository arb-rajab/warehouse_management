"""Run the Vite production build with the remote font plugin switched off.

Older `vite.config.ts` versions fetch Instrument Sans from fonts.bunny.net at
build time. A sandbox whose egress proxy blocks that host cannot finish `npm run build`,
so `public/build/manifest.json` is never written and ~200 Pest feature tests
fail with ViteManifestNotFoundException. The SessionStart hook falls back to
this script when the normal build fails:

    python3 .claude/scripts/build_without_fonts.py

It writes a temporary copy of vite.config.ts without the `bunny` import and the
`fonts: [...]` option, builds with `vite build --config <copy>`, and always
deletes the copy. `vite.config.ts` itself is never modified, so real builds keep
the font. Configurations using only bundled local fonts are built unchanged.
Exits with the build's exit code, or 1 for an unrecognized configuration.
"""

import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CONFIG = ROOT / 'vite.config.ts'
TEMP_CONFIG = ROOT / 'vite.nofonts.config.ts'

BUNNY_IMPORT = re.compile(r"^import \{[^}]*\bbunny\b[^}]*\} from 'laravel-vite-plugin/fonts';\n", re.M)
FONTS_OPTION = re.compile(r'^[ \t]*fonts: \[.*?^[ \t]*\],\n', re.M | re.S)
LOCAL_IMPORT = re.compile(r"^import \{ local \} from 'laravel-vite-plugin/fonts';\n", re.M)


def fontless_config(source: str) -> str:
    """Remove the legacy Bunny provider, preserving bundled local fonts."""
    if LOCAL_IMPORT.search(source) and not BUNNY_IMPORT.search(source):
        return source

    without_import, imports = BUNNY_IMPORT.subn('', source)
    without_fonts, options = FONTS_OPTION.subn('', without_import)

    if imports != 1 or options != 1 or 'bunny' in without_fonts:
        raise ValueError('vite.config.ts no longer has the bunny import and fonts option this script strips')

    return without_fonts


def main() -> int:
    try:
        TEMP_CONFIG.write_text(fontless_config(CONFIG.read_text()))
    except ValueError as error:
        print(error, file=sys.stderr)

        return 1

    try:
        return subprocess.run(['npx', 'vite', 'build', '--config', TEMP_CONFIG.name], cwd=ROOT).returncode
    finally:
        TEMP_CONFIG.unlink(missing_ok=True)


if __name__ == '__main__':
    sys.exit(main())
