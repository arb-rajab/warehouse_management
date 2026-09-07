<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

test('Telescope::check() still enforces the viewTelescope gate in the local environment', function () {
    // The vendor default ORs the gate with app()->environment('local'), which
    // opens Telescope to any visitor — authenticated or not — on every local
    // dev machine, since that's exactly the environment they run under. Our
    // TelescopeServiceProvider::authorization() override must not reintroduce
    // that bypass. Gate::check() resolves the current user from the auth guard
    // (not from $request->user()), so use actingAs() rather than a user resolver.
    app()['env'] = 'local';

    $request = Request::create('/telescope');

    expect(Telescope::check($request))->toBeFalse();

    $mobileUser = User::factory()->mobileUser()->create();
    test()->actingAs($mobileUser);
    expect(Telescope::check($request))->toBeFalse();

    $admin = User::factory()->create();
    test()->actingAs($admin);
    expect(Telescope::check($request))->toBeTrue();
});

test('the telescope user tag never forces a fresh auth lookup', function () {
    $user = User::factory()->create();

    // The exact state that caused infinite recursion in production: the session
    // already identifies a user, but the guard hasn't resolved a User model for
    // it yet. The old tag closure called Auth::check(), which queries "wms_users" to
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

test('the telescope Authorize middleware rejects a guest', function () {
    // The config assertion above only proves the middleware is *listed*. This
    // runs the class Telescope actually puts in front of its routes, which the
    // suite can't reach over HTTP because phpunit.xml sets TELESCOPE_ENABLED=false
    // and the routes are therefore never registered.
    (new Authorize)->handle(Request::create('/telescope'), fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('the telescope Authorize middleware rejects a non-admin user', function () {
    $this->actingAs(User::factory()->mobileUser()->create());

    (new Authorize)->handle(Request::create('/telescope'), fn ($req) => new Response('ok'));
})->throws(HttpException::class);

test('the telescope Authorize middleware admits an admin', function () {
    actingAsAdmin();

    $response = (new Authorize)->handle(Request::create('/telescope'), fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});
