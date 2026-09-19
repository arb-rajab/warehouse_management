<?php

test('responses include the standard secure headers', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'sameorigin');
    $response->assertHeader('Referrer-Policy', 'no-referrer');
    $response->assertHeader('X-Download-Options', 'noopen');
    $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
    $response->assertHeader('Cross-Origin-Embedder-Policy', 'unsafe-none');
    $response->assertHeader('Cross-Origin-Opener-Policy', 'unsafe-none');
    $response->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
});

test('responses carry an HSTS header', function () {
    $response = $this->get('/admin/login');

    expect($response->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});

test('responses carry a Permissions-Policy header', function () {
    $response = $this->get('/admin/login');

    expect($response->headers->get('Permissions-Policy'))->not->toBeEmpty();
});

test('responses carry a content security policy naming every directive we rely on', function () {
    $response = $this->get('/admin/login');

    // Every directive is asserted rather than just the header's presence: the
    // published config ships CSP "enabled" with an entirely empty policy, which
    // compiles to nothing at all and looks switched on in review.
    $csp = $response->headers->get('Content-Security-Policy-Report-Only');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'none'");
});

test('the content security policy is report-only until it has been walked in a browser', function () {
    $response = $this->get('/admin/login');

    $response->assertHeaderMissing('Content-Security-Policy');

    expect(config('secure-headers.csp.report-only'))->toBeTrue();
});

test('headers that are configured to be withheld are absent', function () {
    $response = $this->get('/admin/login');

    $response->assertHeaderMissing('X-Powered-By');
    $response->assertHeaderMissing('Server');
    $response->assertHeaderMissing('X-XSS-Protection');
    $response->assertHeaderMissing('X-DNS-Prefetch-Control');
});

test('a request blocked by the WAF still carries the secure headers', function () {
    // SecureHeaders has to wrap BlockMaliciousRequests: the WAF aborts from
    // inside the pipeline, so anything appended after it never runs on a
    // blocked request and those 403s would ship bare.
    $response = $this->get('/admin/login?search='.urlencode('1 UNION SELECT * FROM users'));

    $response->assertForbidden();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'sameorigin');
    $response->assertHeader('Referrer-Policy', 'no-referrer');
});

test('API responses carry the secure headers too', function () {
    // The middleware is appended globally in bootstrap/app.php, not to the web
    // group, so it must shape API responses as well.
    $response = $this->getJson('/api/v1/there-is-no-such-route');

    $response->assertNotFound();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'no-referrer');
});
