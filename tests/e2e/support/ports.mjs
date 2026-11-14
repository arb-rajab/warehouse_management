import { createServer } from 'node:net';
import { pathToFileURL } from 'node:url';

/**
 * Ports of the Playwright web server, shared by playwright.config.ts,
 * serve-https.mjs and the CI port check.
 *
 * E2E_PORT is the browser-facing port (the HTTPS proxy), default 8010.
 * E2E_BACKEND_PORT is the `php artisan serve` behind it, default E2E_PORT + 1.
 * Several CI runner instances share one machine and may run E2E at the same
 * time, so each sets its own pair: runner N uses E2E_PORT=8010+2N and
 * E2E_BACKEND_PORT=8011+2N. PLAYWRIGHT_BASE_URL, when set, still wins for the
 * browser-facing URL and its port.
 */
export const DEFAULT_E2E_PORT = 8010;

export function portSetting(env, name, fallback) {
    const raw = env[name];

    if (raw === undefined || raw === '') {
        return fallback;
    }

    const value = Number(raw);

    if (!Number.isInteger(value) || value < 1024 || value > 65535) {
        throw new Error(
            `${name} must be a port number from 1024 to 65535, got "${raw}".`,
        );
    }

    return value;
}

export function e2ePorts(env = process.env) {
    const override = env.PLAYWRIGHT_BASE_URL;
    let publicPort;

    if (override) {
        const url = new URL(override);

        publicPort = Number(url.port || (url.protocol === 'https:' ? 443 : 80));
    } else {
        publicPort = portSetting(env, 'E2E_PORT', DEFAULT_E2E_PORT);
    }

    const backendPort = portSetting(env, 'E2E_BACKEND_PORT', publicPort + 1);

    if (backendPort === publicPort) {
        throw new Error(
            `E2E_BACKEND_PORT must differ from the browser-facing port ${publicPort}.`,
        );
    }

    return {
        baseURL: override || `https://127.0.0.1:${publicPort}`,
        publicPort,
        backendPort,
    };
}

function isFree(port) {
    return new Promise((resolve) => {
        const probe = createServer();

        probe.once('error', () => resolve(false));
        probe.once('listening', () => probe.close(() => resolve(true)));
        probe.listen(port, '127.0.0.1');
    });
}

/**
 * `node tests/e2e/support/ports.mjs --check` (CI, before Playwright): fails
 * with the setting to change when a port this run would listen on is taken,
 * typically by another runner instance's E2E job on the same machine.
 */
async function check() {
    const { publicPort, backendPort } = e2ePorts();
    const configured = Boolean(
        process.env.E2E_PORT || process.env.PLAYWRIGHT_BASE_URL,
    );
    const taken = [];

    for (const [name, port] of [
        ['E2E_PORT', publicPort],
        ['E2E_BACKEND_PORT', backendPort],
    ]) {
        if (!(await isFree(port))) {
            taken.push(`${port} (${name})`);
        }
    }

    if (taken.length === 0) {
        console.log(`E2E ports ${publicPort} and ${backendPort} are free.`);

        return;
    }

    const reason = configured
        ? 'Another process, probably another runner instance with the same pair, holds it.'
        : 'E2E_PORT and E2E_BACKEND_PORT are not set on this runner, so it uses the defaults, which another process holds.';

    console.log(
        `::error::E2E port ${taken.join(' and ')} is in use. ${reason} ` +
            'Give every runner instance on this machine its own pair in its .env: ' +
            'runner N uses E2E_PORT=8010+2N and E2E_BACKEND_PORT=8011+2N.',
    );
    process.exitCode = 1;
}

if (
    process.argv[1] &&
    import.meta.url === pathToFileURL(process.argv[1]).href &&
    process.argv[2] === '--check'
) {
    await check();
}
