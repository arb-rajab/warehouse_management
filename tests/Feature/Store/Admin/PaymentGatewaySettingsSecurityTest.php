<?php

use App\Http\Requests\Store\Admin\UpdatePaymentGatewaySettingsRequest;
use App\Http\Requests\Store\Admin\UpdateSmtpSettingsRequest;
use App\Mail\PaymentGatewaySettingsChangedMail;
use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StoreUser;
use App\Services\Store\PaymentGatewaySettingsChangeNotifier;
use App\Support\StorePaymentGateways;
use App\Support\StorePermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;

/*
 * Whoever controls the payment gateway settings can redirect the shop's
 * payments (.ai/rules/admin-env-overrides.md, "Security"): the permission is
 * one only a Super Admin grants, a save asks for the staff member's own
 * password (throttled with the SMTP page in one limiter), and every change is
 * recorded (field names and kinds only) and mailed to the Super Admins.
 */

const PGSS_SECRET = 'pgss-bkash-app-secret';
const PGSS_PASSWORD = 'pgss-bkash-password';
const PGSS_IP = '203.0.113.9';

beforeEach(function () {
    config(['store.enabled_payment_gateways' => [], 'mail.default' => 'array']);
    $this->withServerVariables(['REMOTE_ADDR' => PGSS_IP, 'HTTP_USER_AGENT' => 'Pest browser']);
});

/**
 * @param  array<string, array<string, mixed>>  $gateways
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pgssForm(array $gateways = [], array $overrides = []): array
{
    $form = ['enabled_source' => StorePaymentGateways::enabledSource(), 'gateways' => [], 'current_password' => 'correct-password'];

    foreach (StorePaymentGateways::GATEWAYS as $gateway => $spec) {
        $form['gateways'][$gateway] = isset($spec['mode']['setting']) ? ['mode' => StorePaymentGateways::mode($gateway)] : [];
    }

    foreach ($gateways as $gateway => $fields) {
        $form['gateways'][$gateway] = [...$form['gateways'][$gateway], ...$fields];
    }

    return array_replace($form, $overrides);
}

/** @return array<string, string> */
function pgssBkash(array $overrides = []): array
{
    return ['app_key' => 'ADMINKEY', 'app_secret' => PGSS_SECRET, 'username' => 'admin-user', 'password' => PGSS_PASSWORD, 'currency' => 'BDT', ...$overrides];
}

function pgssSuperAdmin(): StoreUser
{
    return actingAsStoreUser(createStoreAdmin());
}

// the permission

test('payment_methods_configurations is sensitive: staff holding it cannot grant it to a role', function () {
    $permission = Permission::findOrCreate('payment_methods_configurations', 'store');
    actingAsStoreUser(createStoreStaff('add_staff_role', 'payment_methods_configurations'));

    expect(StorePermissions::isSensitive('payment_methods_configurations'))->toBeTrue();

    $this->post(route('roles.store'), ['name' => 'Payments admin', 'permissions' => [$permission->id]])
        ->assertSessionHasErrors(['permissions' => __('messages.role_permission_super_admin_only')]);
    expect(Role::query()->where('name', 'Payments admin')->exists())->toBeFalse();
});

test('an existing grant of payment_methods_configurations is kept: nothing revokes it', function () {
    $staff = createStoreStaff('payment_methods_configurations');
    $role = Role::findOrCreate('Payments clerk', 'store');
    $role->givePermissionTo(Permission::findOrCreate('payment_methods_configurations', 'store'));

    expect($staff->fresh()->can('payment_methods_configurations'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('payment_methods_configurations'))->toBeTrue();
});

test('a Super Admin can grant payment_methods_configurations', function () {
    $permission = Permission::findOrCreate('payment_methods_configurations', 'store');
    actingAsStoreUser(createStoreAdmin());

    $this->post(route('roles.store'), ['name' => 'Payments admin', 'permissions' => [$permission->id]])->assertRedirect(route('roles.index'));
    expect(Role::findByName('Payments admin', 'store')->permissions->modelKeys())->toBe([$permission->id]);
});

// the current password

test('saving without the current password is refused and changes nothing', function () {
    Mail::fake();
    $admin = pgssSuperAdmin();

    $this->post(route('payment_method.update'), pgssForm(['bkash' => pgssBkash()], ['current_password' => '']))
        ->assertSessionHasErrors('current_password')
        ->assertSessionMissing('success');

    expect(DB::table('payment_gateway_settings')->count())->toBe(0)
        ->and(AdminAuditLog::query()->count())->toBe(0)
        ->and(RateLimiter::attempts(UpdatePaymentGatewaySettingsRequest::passwordLimiterKey($admin)))->toBe(0);
    Mail::assertNothingOutgoing();
});

test('a wrong current password is refused, counted, logged and recorded without any value', function () {
    Mail::fake();
    Log::spy();
    $admin = pgssSuperAdmin();

    $this->post(route('payment_method.update'), pgssForm(['bkash' => pgssBkash()], ['current_password' => 'wrong-password']))
        ->assertSessionHasErrors(['current_password' => __('validation.current_password')]);

    expect(DB::table('payment_gateway_settings')->count())->toBe(0)
        ->and(RateLimiter::attempts(UpdatePaymentGatewaySettingsRequest::passwordLimiterKey($admin)))->toBe(1);
    $row = AdminAuditLog::query()->sole();
    expect($row->action)->toBe(AdminAuditLog::PAYMENT_GATEWAY_SETTINGS_PASSWORD_FAILED)
        ->and($row->changed_fields)->toBe([])
        ->and($row->ip_address)->toBe(PGSS_IP)
        ->and(json_encode($row->getAttributes()))->not->toContain('wrong-password')->not->toContain(PGSS_SECRET)->not->toContain('ADMINKEY');
    Log::shouldHaveReceived('warning')->once()->with('A wrong current password was entered on the Payment methods page.', ['user_id' => $admin->id, 'ip' => PGSS_IP]);
    Mail::assertNothingOutgoing();
});

test('the wrong passwords of the SMTP and payments pages share one counter', function () {
    $admin = pgssSuperAdmin();

    expect(UpdatePaymentGatewaySettingsRequest::passwordLimiterKey($admin))->toBe(UpdateSmtpSettingsRequest::passwordLimiterKey($admin));

    foreach (range(1, UpdatePaymentGatewaySettingsRequest::MAX_PASSWORD_FAILURES) as $ignored) {
        RateLimiter::hit(UpdateSmtpSettingsRequest::passwordLimiterKey($admin), UpdateSmtpSettingsRequest::PASSWORD_FAILURE_DECAY_SECONDS);
    }

    // Locked out by the SMTP page: the payments page refuses even the right password.
    $this->post(route('payment_method.update'), pgssForm(['bkash' => pgssBkash()]))
        ->assertSessionHasErrors('current_password');
    expect(DB::table('payment_gateway_settings')->count())->toBe(0);
});

test('a locked-out account adds no audit rows beyond the limit', function () {
    $admin = pgssSuperAdmin();

    foreach (range(1, UpdatePaymentGatewaySettingsRequest::MAX_PASSWORD_FAILURES + 2) as $ignored) {
        $this->post(route('payment_method.update'), pgssForm([], ['current_password' => 'wrong-password']))->assertSessionHasErrors('current_password');
    }

    expect(AdminAuditLog::query()->where('action', AdminAuditLog::PAYMENT_GATEWAY_SETTINGS_PASSWORD_FAILED)->count())
        ->toBe(UpdatePaymentGatewaySettingsRequest::MAX_PASSWORD_FAILURES);
});

test('neither the current password nor a secret is ever flashed back', function () {
    pgssSuperAdmin();

    $this->post(route('payment_method.update'), pgssForm(['bkash' => pgssBkash(['currency' => 'TAKA'])], ['current_password' => 'wrong-password']))
        ->assertSessionHasErrors(['current_password', 'gateways.bkash.currency']);

    expect(session()->getOldInput())->not->toHaveKey('current_password')->not->toHaveKey('gateways')
        ->and(json_encode(session()->all()))->not->toContain('wrong-password')->not->toContain(PGSS_SECRET)->not->toContain(PGSS_PASSWORD);
});

// the audit row and the notification

test('a save that changes something writes one audit row with field names and kinds only', function () {
    Mail::fake();
    $admin = pgssSuperAdmin();

    $this->post(route('payment_method.update'), pgssForm(['bkash' => [...pgssBkash(), 'enabled' => '1', 'mode' => 'live']], ['enabled_source' => 'admin']))
        ->assertSessionHasNoErrors();

    $row = AdminAuditLog::query()->where('action', AdminAuditLog::PAYMENT_GATEWAY_SETTINGS_UPDATED)->sole();
    expect($row->user_id)->toBe($admin->id)
        ->and($row->changed_fields)->toBe([
            'enabled_source' => 'set',
            'bkash.enabled' => 'enabled',
            'bkash.mode' => 'changed',
            'bkash.app_key' => 'set',
            'bkash.app_secret' => 'set',
            'bkash.username' => 'set',
            'bkash.password' => 'set',
            'bkash.currency' => 'set',
        ])
        ->and(json_encode($row->getAttributes()))->not->toContain(PGSS_SECRET)->not->toContain(PGSS_PASSWORD)->not->toContain('ADMINKEY')->not->toContain('admin-user');
});

test('a changed secret, a removed group and a disabled gateway are recorded as such', function () {
    Mail::fake();
    pgssSuperAdmin();
    $this->post(route('payment_method.update'), pgssForm(['bkash' => [...pgssBkash(), 'enabled' => '1']], ['enabled_source' => 'admin']))->assertSessionHasNoErrors();

    $this->post(route('payment_method.update'), pgssForm(['bkash' => [...pgssBkash(['app_secret' => 'another-secret', 'password' => '']), 'enabled' => '1']]))->assertSessionHasNoErrors();
    expect(AdminAuditLog::query()->latest('id')->first()->changed_fields)->toBe(['bkash.app_secret' => 'changed']);

    $this->post(route('payment_method.update'), pgssForm(['bkash' => ['enabled' => '0']]))->assertSessionHasNoErrors();
    expect(AdminAuditLog::query()->latest('id')->first()->changed_fields)->toBe([
        'bkash.enabled' => 'disabled',
        'bkash.app_key' => 'removed', 'bkash.app_secret' => 'removed', 'bkash.username' => 'removed',
        'bkash.password' => 'removed', 'bkash.currency' => 'removed',
    ]);
});

test('a save that changes nothing records and mails nothing, but still says it was saved', function () {
    Mail::fake();
    pgssSuperAdmin();
    $this->post(route('payment_method.update'), pgssForm(['bkash' => pgssBkash()]))->assertSessionHasNoErrors();
    $rows = AdminAuditLog::query()->count();
    Mail::fake();

    // The same values again, the secrets typed again identically or left empty.
    $this->post(route('payment_method.update'), pgssForm(['bkash' => pgssBkash()]))
        ->assertInertiaFlash('success', __('store_admin.payment_method_settings_have_been_updated_successfully'));
    $this->post(route('payment_method.update'), pgssForm(['bkash' => pgssBkash(['app_secret' => '', 'password' => ''])]))->assertSessionHasNoErrors();

    expect(AdminAuditLog::query()->count())->toBe($rows);
    Mail::assertNothingOutgoing();
});

test('the first save of an untouched page records nothing either', function () {
    Mail::fake();
    pgssSuperAdmin();

    $this->post(route('payment_method.update'), pgssForm())->assertSessionHasNoErrors();

    expect(AdminAuditLog::query()->count())->toBe(0);
    Mail::assertNothingOutgoing();
});

test('every active Super Admin is mailed the change, with the field names and no value', function () {
    Mail::fake();
    $actor = pgssSuperAdmin();
    $other = createStoreAdmin();
    $banned = createStoreAdmin();
    $banned->forceFill(['banned' => 1])->save();
    createStoreStaff('payment_methods_configurations');

    $this->post(route('payment_method.update'), pgssForm(['bkash' => pgssBkash()]))->assertSessionHasNoErrors();

    Mail::assertSent(PaymentGatewaySettingsChangedMail::class, 2);
    foreach ([$actor, $other] as $admin) {
        Mail::assertSent(PaymentGatewaySettingsChangedMail::class, fn (PaymentGatewaySettingsChangedMail $mail) => $mail->hasTo($admin->email));
    }
    Mail::assertNotSent(PaymentGatewaySettingsChangedMail::class, fn (PaymentGatewaySettingsChangedMail $mail) => $mail->hasTo($banned->email));

    $mail = Mail::sent(PaymentGatewaySettingsChangedMail::class)->first();
    $html = $mail->render();
    expect($mail->array['changes'])->toHaveKey('bkash.app_secret')
        ->and($html)->toContain('bKash')->toContain(PGSS_IP)
        ->and($html)->not->toContain(PGSS_SECRET)->not->toContain(PGSS_PASSWORD)->not->toContain('ADMINKEY')->not->toContain('admin-user');
});

test('a notification that cannot be sent is logged by class and never undoes the save', function () {
    Log::spy();
    $actor = pgssSuperAdmin();
    Mail::shouldReceive('to')->andThrow(new TransportException('auth failed for admin-user'));

    app(PaymentGatewaySettingsChangeNotifier::class)->notify($actor, ['bkash.app_key' => 'set'], PGSS_IP, now());

    Log::shouldHaveReceived('warning')->with('The payment gateway change notification could not be sent.', ['exception' => TransportException::class]);
});

test('the page lists the last saves and refused passwords, without values or the browser', function () {
    Mail::fake();
    pgssSuperAdmin();
    $this->post(route('payment_method.update'), pgssForm(['bkash' => pgssBkash()]))->assertSessionHasNoErrors();
    $this->post(route('payment_method.update'), pgssForm([], ['current_password' => 'wrong-password']));

    $this->get(route('payment_method.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('auditEntries', 2)
        ->where('auditEntries.0.action', AdminAuditLog::PAYMENT_GATEWAY_SETTINGS_PASSWORD_FAILED)
        ->where('auditEntries.1.action', AdminAuditLog::PAYMENT_GATEWAY_SETTINGS_UPDATED)
        ->where('auditEntries.1.changed_fields', fn ($fields): bool => (collect($fields)->all()['bkash.app_secret'] ?? null) === 'set')
        ->missing('auditEntries.0.user_agent'));
});
