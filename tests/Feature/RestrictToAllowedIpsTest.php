<?php

use App\Http\Middleware\RestrictToAllowedIps;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('RestrictToAllowedIps passes requests from an allowed IP', function () {
    config(['telescope.allowed_ips' => '10.0.0.5,10.0.0.6']);

    $request = Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '10.0.0.5']);

    $response = (new RestrictToAllowedIps)->handle($request, fn ($req) => new Response('ok'), 'telescope.allowed_ips');

    expect($response->getContent())->toBe('ok');
});

test('RestrictToAllowedIps blocks requests from a disallowed IP', function () {
    config(['telescope.allowed_ips' => '10.0.0.5']);

    $request = Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '203.0.113.1']);

    (new RestrictToAllowedIps)->handle($request, fn ($req) => new Response('ok'), 'telescope.allowed_ips');
})->throws(HttpException::class);

test('RestrictToAllowedIps allows any IP when the allowlist is empty', function () {
    config(['telescope.allowed_ips' => '']);

    $request = Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '203.0.113.1']);

    $response = (new RestrictToAllowedIps)->handle($request, fn ($req) => new Response('ok'), 'telescope.allowed_ips');

    expect($response->getContent())->toBe('ok');
});

test('RestrictToAllowedIps reads the allowlist from the config key it is given', function () {
    config(['pulse.allowed_ips' => '10.0.0.9']);

    $request = Request::create('/pulse', 'GET', server: ['REMOTE_ADDR' => '10.0.0.9']);

    $response = (new RestrictToAllowedIps)->handle($request, fn ($req) => new Response('ok'), 'pulse.allowed_ips');

    expect($response->getContent())->toBe('ok');
});

test('RestrictToAllowedIps accepts a CIDR range entry', function () {
    config(['telescope.allowed_ips' => '10.0.0.0/24']);

    $request = Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '10.0.0.42']);

    $response = (new RestrictToAllowedIps)->handle($request, fn ($req) => new Response('ok'), 'telescope.allowed_ips');

    expect($response->getContent())->toBe('ok');
});

test('RestrictToAllowedIps blocks an IP outside a CIDR range entry', function () {
    config(['telescope.allowed_ips' => '10.0.0.0/24']);

    $request = Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '10.0.1.42']);

    (new RestrictToAllowedIps)->handle($request, fn ($req) => new Response('ok'), 'telescope.allowed_ips');
})->throws(HttpException::class);

test('RestrictToAllowedIps still accepts the documented wildcard form', function () {
    config(['telescope.allowed_ips' => '10.0.0.*']);

    $request = Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '10.0.0.42']);

    $response = (new RestrictToAllowedIps)->handle($request, fn ($req) => new Response('ok'), 'telescope.allowed_ips');

    expect($response->getContent())->toBe('ok');
});

test('RestrictToAllowedIps tolerates whitespace around entries', function () {
    config(['telescope.allowed_ips' => ' 10.0.0.5 , 10.0.0.6 ']);

    $request = Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '10.0.0.6']);

    $response = (new RestrictToAllowedIps)->handle($request, fn ($req) => new Response('ok'), 'telescope.allowed_ips');

    expect($response->getContent())->toBe('ok');
});

test('RestrictToAllowedIps cannot be bypassed with a forged X-Forwarded-For header', function () {
    // $request->ip() reads X-Forwarded-For only for proxies TRUSTED_PROXIES names
    // in bootstrap/app.php. With none configured it must fall back to REMOTE_ADDR,
    // so a client cannot talk its way onto the allowlist with a header. Setting
    // TRUSTED_PROXIES to "*" would break exactly this property.
    expect(getenv('TRUSTED_PROXIES'))->toBeFalse('this test only holds while no proxies are trusted in the test environment');

    config(['telescope.allowed_ips' => '10.0.0.5']);

    $request = Request::create('/telescope', 'GET', server: [
        'REMOTE_ADDR' => '203.0.113.1',
        'HTTP_X_FORWARDED_FOR' => '10.0.0.5',
    ]);

    (new RestrictToAllowedIps)->handle($request, fn ($req) => new Response('ok'), 'telescope.allowed_ips');
})->throws(HttpException::class);
