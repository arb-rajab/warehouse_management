<?php

test('responses include the standard secure headers', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'sameorigin');
    $response->assertHeader('Referrer-Policy', 'no-referrer');
});
