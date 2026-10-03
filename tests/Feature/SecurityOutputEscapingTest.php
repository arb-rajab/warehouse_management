<?php

use App\Models\Blog;

test('blog page with script tag renders sanitized content', function () {
    $blog = Blog::factory()->create([
        'description' => '<p>Safe content</p><script>alert("xss")</script><p>More safe</p>',
    ]);

    $response = $this->get(route('blog.details', $blog->slug));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toContain('<p>Safe content</p>')
        ->and($response->getContent())->toContain('<p>More safe</p>')
        ->and($response->getContent())->not->toContain('alert');
});

test('blog page with onclick attribute renders sanitized', function () {
    $blog = Blog::factory()->create([
        'description' => '<div onclick="alert(1)">Click me</div><p>Safe</p>',
    ]);

    $response = $this->get(route('blog.details', $blog->slug));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->not->toContain('onclick')
        ->and($response->getContent())->toContain('Click me')
        ->and($response->getContent())->toContain('<p>Safe</p>');
});
