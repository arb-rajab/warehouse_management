<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

test('an admin passes the viewTelescope gate', function () {
    $admin = actingAsAdmin();

    expect(Gate::allows('viewTelescope', $admin))->toBeTrue();
});

test('a non-admin user fails the viewTelescope gate', function () {
    $user = User::factory()->mobileUser()->create();

    expect(Gate::allows('viewTelescope', $user))->toBeFalse();
});

test('a guest fails the viewTelescope gate', function () {
    expect(Gate::allows('viewTelescope'))->toBeFalse();
});

test('the telescope route middleware is IP-restricted via telescope.allowed_ips', function () {
    expect(config('telescope.middleware'))->toContain('App\Http\Middleware\RestrictToAllowedIps:telescope.allowed_ips');
});

test('the telescope user tag never forces a fresh auth lookup', function () {
    $user = User::factory()->create();

    // The exact state that caused infinite recursion in production: the session
    // already identifies a user, but the guard hasn't resolved a User model for
    // it yet. The old tag closure called Auth::check(), which queries "users" to
    // resolve the guard — and Telescope then tries to tag *that* query too,
    // re-entering this closure before the first lookup finishes, recursing until
    // PHP's max_execution_time kills the request.
    session([Auth::guard('web')->getName() => $user->id]);

    DB::enableQueryLog();

    $tags = collect(Telescope::$tagUsing)
        ->flatMap(fn ($tag) => $tag(IncomingEntry::make([])))
        ->all();

    expect(DB::getQueryLog())->toBeEmpty()
        ->and($tags)->toBeEmpty();
});

test('the telescope user tag includes the resolved user once one exists', function () {
    $user = actingAsAdmin();

    $tags = collect(Telescope::$tagUsing)
        ->flatMap(fn ($tag) => $tag(IncomingEntry::make([])))
        ->all();

    expect($tags)->toContain('User:'.$user->id);
});
