<?php

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The store's Inertia pages and its unconverted Blade pages share /temp
 * (.ai/rules/store-backoffice-migration.md §2.5-2.6): an Inertia visit that
 * lands on a Blade page becomes a full page load, and only Blade pages get
 * the inline-script Content-Security-Policy.
 */

/**
 * The response's script-src directive (report-only outside production).
 */
function scriptSrcOf(TestResponse $response): string
{
    $policy = $response->headers->get('Content-Security-Policy-Report-Only')
        ?? $response->headers->get('Content-Security-Policy');

    return collect(explode(';', (string) $policy))
        ->map(fn (string $directive) => trim($directive))
        ->first(fn (string $directive) => str_starts_with($directive, 'script-src ')) ?? '';
}

test('an Inertia visit that lands on a Blade page is sent to load it in full', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/general-setting', inertiaHeaders())
        ->assertConflict()
        ->assertHeader('X-Inertia-Location', url('/temp/admin/general-setting'));
});

test('a plain visit to a Blade page renders it, under the inline-script policy', function () {
    $admin = actingAsStoreUser(createStoreAdmin());

    $response = $this->get('/temp/admin/general-setting')->assertOk()->assertSee($admin->name);

    expect(scriptSrcOf($response))
        ->toContain("'self'")
        ->toContain("'unsafe-inline'")
        ->toContain('https:');
});

test('an Inertia store page keeps the strict policy, on a full load and on an Inertia visit', function () {
    actingAsStoreUser(createStoreStaff('view_all_staffs'));

    $fullLoad = $this->get('/temp/admin/staffs')->assertOk();
    $visit = $this->get('/temp/admin/staffs', inertiaHeaders())
        ->assertOk()
        ->assertHeader('X-Inertia', 'true');

    expect(scriptSrcOf($fullLoad))->toBe("script-src 'self'")
        ->and(scriptSrcOf($visit))->toBe("script-src 'self'");
});

test("a Blade page's policy ends with its request", function () {
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/general-setting')->assertOk();

    expect(scriptSrcOf($this->get('/temp/admin/staffs')->assertOk()))->toBe("script-src 'self'");
});

test('a warehouse page keeps the strict policy', function () {
    expect(scriptSrcOf($this->get('/admin/login')->assertOk()))->toBe("script-src 'self'");
});

test('a redirect out of an Inertia form is left to Inertia to follow', function () {
    actingAsStoreUser(createStoreStaff('add_staff'));

    $this->post('/temp/admin/staffs', [], inertiaHeaders())
        ->assertRedirect()
        ->assertSessionHasErrors('name');
});

test('an Inertia store page renders inside the root view as usual', function () {
    actingAsStoreUser(createStoreStaff('view_all_staffs'));

    $this->get('/temp/admin/staffs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Staff/Staffs/Index'));
});
