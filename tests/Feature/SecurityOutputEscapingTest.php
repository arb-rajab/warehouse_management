<?php

use App\Services\HtmlSanitizer;

test('clean_html helper sanitizes script tags', function () {
    $dirty = '<p>Safe content</p><script>alert("xss")</script><p>More safe</p>';
    $clean = clean_html($dirty);

    expect($clean)->toContain('Safe content')
        ->and($clean)->toContain('More safe')
        ->and($clean)->not->toContain('script')
        ->and($clean)->not->toContain('alert');
});

test('clean_html helper removes onclick attributes', function () {
    $dirty = '<div onclick="alert(1)">Click me</div><p>Safe</p>';
    $clean = clean_html($dirty);

    expect($clean)->not->toContain('onclick')
        ->and($clean)->toContain('Click me')
        ->and($clean)->toContain('Safe');
});

test('html sanitizer is applied to user content in views', function () {
    $service = app(HtmlSanitizer::class);
    $html = '<p>Text</p><img src=x onerror="alert(1)" />';
    $cleaned = $service->clean($html);

    expect($cleaned)->not->toContain('onerror');
});
