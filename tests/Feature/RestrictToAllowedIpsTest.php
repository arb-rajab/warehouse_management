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
