<?php

use App\Http\Middleware\BlockMaliciousRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('passes a clean request through', function () {
    $request = Request::create('/login', 'GET');

    $response = (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('blocks a SQL injection payload in the query string', function () {
    $request = Request::create('/login', 'GET', ['id' => '1 OR 1=1']);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks an XSS payload in the request body', function () {
    $request = Request::create('/login', 'POST', ['comment' => '<script>alert(1)</script>']);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks a path traversal attempt in the URI', function () {
    $request = Request::create('/files/../../etc/passwd', 'GET');

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('skips the check for excluded paths', function () {
    config(['waf.exclude_paths' => ['telescope*']]);

    $request = Request::create('/telescope/requests', 'GET', ['id' => '1 OR 1=1']);

    $response = (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('allows every request when disabled', function () {
    config(['waf.enabled' => false]);

    $request = Request::create('/login', 'GET', ['id' => '1 OR 1=1']);

    $response = (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('a real request carrying a SQL injection payload is rejected with a 403', function () {
    $response = $this->get('/login?search='.urlencode('1 UNION SELECT * FROM users'));

    $response->assertForbidden();
});

test('the login page still renders normally through the full middleware stack', function () {
    $response = $this->get('/login');

    $response->assertOk();
});
