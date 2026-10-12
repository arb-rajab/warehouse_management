import { execFileSync, spawn } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import http from 'node:http';
import https from 'node:https';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

// Enforced CSP upgrades Inertia redirects to HTTPS. Serve real TLS locally
// instead of weakening the application's policy for browser tests.
const publicURL = new URL(process.env.APP_URL);
const port = Number(publicURL.port || 443);
const backendPort = port + 1;
const certificateDirectory = mkdtempSync(join(tmpdir(), 'wms-e2e-tls-'));
const keyPath = join(certificateDirectory, 'key.pem');
const certificatePath = join(certificateDirectory, 'certificate.pem');

try {
    execFileSync(
        'openssl',
        [
            'req',
            '-x509',
            '-newkey',
            'rsa:2048',
            '-nodes',
            '-days',
            '1',
            '-keyout',
            keyPath,
            '-out',
            certificatePath,
            '-subj',
            '/CN=localhost',
            '-addext',
            'subjectAltName=DNS:localhost,IP:127.0.0.1',
        ],
        { stdio: 'ignore' },
    );
} catch (error) {
    rmSync(certificateDirectory, { recursive: true, force: true });

    throw error;
}

const backend = spawn(
    'php',
    [
        'artisan',
        'serve',
        '--no-reload',
        '--host=127.0.0.1',
        `--port=${backendPort}`,
        '--tries=1',
    ],
    { stdio: 'inherit', detached: process.platform !== 'win32' },
);

const sockets = new Set();
const server = https.createServer(
    {
        key: readFileSync(keyPath),
        cert: readFileSync(certificatePath),
    },
    (request, response) => {
        const upstream = http.request(
            {
                hostname: '127.0.0.1',
                port: backendPort,
                // PHP's development server closes each response. Do not pool
                // sockets that it has already closed between browser requests.
                agent: false,
                method: request.method,
                path: request.url,
                headers: {
                    ...request.headers,
                    connection: 'close',
                    host: publicURL.host,
                    'x-forwarded-host': publicURL.host,
                    'x-forwarded-proto': 'https',
                    'x-forwarded-port': String(port),
                    'x-forwarded-for': '127.0.0.1',
                },
            },
            (upstreamResponse) => {
                const headers = { ...upstreamResponse.headers };

                // The PHP connection is intentionally closed after each response;
                // the independent TLS connection can remain alive in Chromium.
                for (const name of [
                    'connection',
                    'keep-alive',
                    'transfer-encoding',
                    'proxy-authenticate',
                    'proxy-authorization',
                    'te',
                    'trailer',
                    'upgrade',
                ]) {
                    delete headers[name];
                }

                response.writeHead(upstreamResponse.statusCode, headers);
                upstreamResponse.pipe(response);
            },
        );

        upstream.on('error', () => {
            if (!response.headersSent) {
                response.writeHead(502);
            }

            response.end('The local PHP test server is unavailable.');
        });
        request.on('aborted', () => upstream.destroy());
        request.pipe(upstream);
    },
);

server.on('connection', (socket) => {
    sockets.add(socket);
    socket.on('close', () => sockets.delete(socket));
});

let stopping = false;
function stop(code = 0) {
    if (stopping) {
        return;
    }

    stopping = true;
    server.close();

    for (const socket of sockets) {
        socket.destroy();
    }

    if (backend.pid) {
        try {
            if (process.platform === 'win32') {
                backend.kill('SIGTERM');
            } else {
                process.kill(-backend.pid, 'SIGTERM');
            }
        } catch (error) {
            if (error.code !== 'ESRCH') {
                throw error;
            }
        }
    }

    rmSync(certificateDirectory, { recursive: true, force: true });
    process.exit(code);
}

backend.on('error', (error) => {
    console.error(error.message);
    stop(1);
});
backend.on('exit', (code) => stop(code ?? 1));
server.on('error', (error) => {
    console.error(error.message);
    stop(1);
});
process.on('SIGTERM', () => stop());
process.on('SIGINT', () => stop());
server.listen(port, '127.0.0.1');
