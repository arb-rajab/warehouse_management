<?php

use App\Http\Requests\Store\Admin\UpdateSmtpSettingsRequest;
use App\Mail\SmtpSettingsChangedMail;
use App\Models\AdminAuditLog;
use App\Models\StoreUser;
use App\Services\Store\SmtpSettingsChangeNotifier;
use App\Support\StoreMailSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;

/*
 * Whoever controls the SMTP settings can read every email the shop sends,
 * password resets included (.ai/rules/admin-env-overrides.md, "Security"):
 * a save asks for the staff member's own password, wrong ones are throttled
 * and recorded, and every change is recorded (field names only) and mailed to
 * the Super Admins, and to the previous from-address when that changed,
 * through the settings as they were before the change.
 */

const SECURE_SMTP_SECRET = 'S3cret-smtp-pass!';
const SECURE_ENV_SECRET = 'env-secret-password';
const SECURE_IP = '203.0.113.7';

beforeEach(function () {
    // The server's `.env` values: SMTP, so the "previous settings" mailer is the runtime copy.
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.url' => null,
        'mail.mailers.smtp.host' => 'env.example.com',
        'mail.mailers.smtp.port' => 2525,
        'mail.mailers.smtp.scheme' => null,
        'mail.mailers.smtp.username' => 'env-user',
        'mail.mailers.smtp.password' => SECURE_ENV_SECRET,
        'mail.from.address' => 'env@example.com',
        'mail.from.name' => 'Env Shop',
    ]);
    $this->withServerVariables(['REMOTE_ADDR' => SECURE_IP, 'HTTP_USER_AGENT' => 'Pest browser']);
});

/** @return array<string, mixed> */
function secureSmtpForm(array $overrides = []): array
{
    return [
        'mailer' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'scheme' => '',
        'username' => 'shop-user',
        'password' => SECURE_SMTP_SECRET,
        'from_address' => 'shop@example.com',
        'from_name' => 'Shop',
        'current_password' => 'correct-password',
        ...$overrides,
    ];
}

function secureSmtpRows(): array
{
    return DB::table('business_settings')->whereIn('type', array_values(StoreMailSettings::KEYS))->pluck('value', 'type')->all();
}

/** A Super Admin who is signed in; their password is `correct-password`. */
function signedInSuperAdmin(): StoreUser
{
    return actingAsStoreUser(createStoreAdmin());
}

// the current password

test('saving without the current password is refused on its field and changes nothing', function () {
    Mail::fake();
    signedInSuperAdmin();

    $this->post(route('smtp_settings.update'), secureSmtpForm(['current_password' => '']))
        ->assertSessionHasErrors('current_password')
        ->assertSessionMissing('success');

    expect(secureSmtpRows())->toBe([])
        ->and(AdminAuditLog::query()->count())->toBe(0)
        ->and(RateLimiter::attempts(UpdateSmtpSettingsRequest::passwordLimiterKey(auth('store')->user())))->toBe(0);
    Mail::assertNothingOutgoing();
});

test('a wrong current password is refused, saves nothing, is counted and recorded without any value', function () {
    Mail::fake();
    Log::spy();
    $admin = signedInSuperAdmin();

    $this->post(route('smtp_settings.update'), secureSmtpForm(['current_password' => 'wrong-password']))
        ->assertSessionHasErrors(['current_password' => __('validation.current_password')]);

    expect(secureSmtpRows())->toBe([])
        ->and(RateLimiter::attempts(UpdateSmtpSettingsRequest::passwordLimiterKey($admin)))->toBe(1);
    $row = AdminAuditLog::query()->sole();
    expect($row->action)->toBe(AdminAuditLog::SMTP_SETTINGS_PASSWORD_FAILED)
        ->and($row->user_id)->toBe($admin->id)
        ->and($row->changed_fields)->toBe([])
        ->and($row->ip_address)->toBe(SECURE_IP)
        ->and(json_encode($row->getAttributes()))->not->toContain('wrong-password')->not->toContain(SECURE_SMTP_SECRET)->not->toContain('smtp.example.com');
    Log::shouldHaveReceived('warning')->once()->with('A wrong current password was entered on the SMTP settings page.', ['user_id' => $admin->id, 'ip' => SECURE_IP]);
    Mail::assertNothingOutgoing();
});

test('the current password is never flashed back to the form', function () {
    signedInSuperAdmin();

    $this->post(route('smtp_settings.update'), secureSmtpForm(['current_password' => 'wrong-password', 'from_name' => str_repeat('x', 256)]))
        ->assertSessionHasErrors(['current_password', 'from_name']);

    expect(session()->getOldInput())->not->toHaveKey('current_password')
        ->and(json_encode(session()->all()))->not->toContain('wrong-password');
});

test('the current password is checked on the store guard, against the signed-in staff member only', function () {
    signedInSuperAdmin();
    createStoreUser('admin', password: 'someone-elses-password');

    $this->post(route('smtp_settings.update'), secureSmtpForm(['current_password' => 'someone-elses-password']))
        ->assertSessionHasErrors('current_password');
    expect(secureSmtpRows())->toBe([]);
});

test('the right current password saves, flashes success and clears the failure count', function () {
    Mail::fake();
    $admin = signedInSuperAdmin();
    RateLimiter::hit(UpdateSmtpSettingsRequest::passwordLimiterKey($admin), 900);

    $this->post(route('smtp_settings.update'), secureSmtpForm())
        ->assertRedirectToRoute('smtp_settings.index')
        ->assertInertiaFlash('success', __('store_admin.smtp_settings_have_been_updated_successfully'));

    expect(secureSmtpRows())->toHaveKey('mail_host', 'smtp.example.com')
        ->and(secureSmtpRows())->not->toHaveKey('current_password')
        ->and(RateLimiter::attempts(UpdateSmtpSettingsRequest::passwordLimiterKey($admin)))->toBe(0);
});

test('after five wrong passwords even the right one is refused for a while, and the audit log stops growing', function () {
    Mail::fake();
    $admin = signedInSuperAdmin();

    foreach (range(1, UpdateSmtpSettingsRequest::MAX_PASSWORD_FAILURES) as $attempt) {
        $this->post(route('smtp_settings.update'), secureSmtpForm(['current_password' => 'wrong-'.$attempt]))
            ->assertSessionHasErrors(['current_password' => __('validation.current_password')]);
    }

    $this->post(route('smtp_settings.update'), secureSmtpForm())->assertSessionHasErrors('current_password');
    expect(session('errors')->first('current_password'))->toStartWith('Too many wrong passwords.')
        ->and(secureSmtpRows())->toBe([])
        ->and(AdminAuditLog::query()->where('action', AdminAuditLog::SMTP_SETTINGS_PASSWORD_FAILED)->count())->toBe(UpdateSmtpSettingsRequest::MAX_PASSWORD_FAILURES);

    // Another account is not locked out.
    actingAsStoreUser(createStoreAdmin());
    $this->post(route('smtp_settings.update'), secureSmtpForm())->assertSessionHasNoErrors();

    // The window passes.
    $this->travel(UpdateSmtpSettingsRequest::PASSWORD_FAILURE_DECAY_SECONDS + 1)->seconds();
    actingAsStoreUser($admin);
    $this->post(route('smtp_settings.update'), secureSmtpForm(['from_name' => 'Later']))->assertSessionHasNoErrors();
});

test('a failure counted beyond the limit (a burst of parallel requests) is not recorded', function () {
    $admin = signedInSuperAdmin();
    $key = UpdateSmtpSettingsRequest::passwordLimiterKey($admin);

    // Requests that passed the lockout check together, then all failed.
    foreach (range(1, UpdateSmtpSettingsRequest::MAX_PASSWORD_FAILURES) as $ignored) {
        RateLimiter::hit($key, 900);
    }
    $request = UpdateSmtpSettingsRequest::create('/', 'POST', ['current_password' => 'wrong']);
    $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => $admin);
    $validator = validator(['current_password' => 'wrong'], ['current_password' => ['current_password:store']]);
    $validator->fails();

    expect(fn () => (new ReflectionMethod($request, 'failedValidation'))->invoke($request, $validator))->toThrow(ValidationException::class)
        ->and(AdminAuditLog::query()->count())->toBe(0)
        ->and(RateLimiter::attempts($key))->toBe(UpdateSmtpSettingsRequest::MAX_PASSWORD_FAILURES + 1);
});

test('the test email does not ask for the current password', function () {
    Mail::fake();
    actingAsStoreUser(createStoreStaff('smtp_settings', 'send_newsletter'));

    $this->post(route('test.smtp'), ['email' => 'recipient@example.com'])->assertSessionHasNoErrors()->assertRedirectToRoute('smtp_settings.index');
});

// the audit row

test('a save writes one audit row with each changed field and how it changed, never a value', function () {
    Mail::fake();
    $admin = signedInSuperAdmin();

    $this->post(route('smtp_settings.update'), secureSmtpForm())->assertSessionHasNoErrors();

    $row = AdminAuditLog::query()->sole();
    expect($row->action)->toBe(AdminAuditLog::SMTP_SETTINGS_UPDATED)
        ->and($row->changed_fields)->toEqual([
            'mailer' => 'set', 'host' => 'set', 'port' => 'set', 'username' => 'set',
            'password' => 'set', 'from_address' => 'set', 'from_name' => 'set',
        ])
        ->and($row->user_id)->toBe($admin->id)
        ->and($row->user_name)->toBe($admin->name)
        ->and($row->user_email)->toBe($admin->email)
        ->and($row->ip_address)->toBe(SECURE_IP)
        ->and($row->user_agent)->toBe('Pest browser');

    $stored = json_encode($row->getAttributes());
    foreach ([SECURE_SMTP_SECRET, 'smtp.example.com', 'shop-user', 'shop@example.com', '587', 'correct-password'] as $value) {
        expect($stored)->not->toContain($value);
    }
});

test('a change records changed, a cleared field removed, and unchanged fields nothing', function () {
    Mail::fake();
    signedInSuperAdmin();
    $this->post(route('smtp_settings.update'), secureSmtpForm())->assertSessionHasNoErrors();

    // Same password typed again: not a change. New port, from name cleared.
    $this->post(route('smtp_settings.update'), secureSmtpForm(['port' => 465, 'from_name' => '']))->assertSessionHasNoErrors();

    expect(AdminAuditLog::query()->latest('id')->first()->changed_fields)->toEqual(['port' => 'changed', 'from_name' => 'removed']);
});

test('removing the saved password and clearing the server group are recorded and mailed', function () {
    Mail::fake();
    $admin = signedInSuperAdmin();
    $this->post(route('smtp_settings.update'), secureSmtpForm())->assertSessionHasNoErrors();
    Mail::fake();

    $this->post(route('smtp_settings.update'), secureSmtpForm(['password' => '', 'password_clear' => '1']))->assertSessionHasNoErrors();
    expect(AdminAuditLog::query()->latest('id')->first()->changed_fields)->toEqual(['password' => 'removed']);

    $this->post(route('smtp_settings.update'), secureSmtpForm(['host' => '', 'port' => '', 'username' => '', 'password' => '']))->assertSessionHasNoErrors();
    expect(AdminAuditLog::query()->latest('id')->first()->changed_fields)->toEqual(['host' => 'removed', 'port' => 'removed', 'username' => 'removed']);

    Mail::assertSent(SmtpSettingsChangedMail::class, 2);
    Mail::assertSent(SmtpSettingsChangedMail::class, fn (SmtpSettingsChangedMail $mail) => $mail->hasTo($admin->email)
        && $mail->array['changes'] === ['password' => 'removed'] && str_contains($mail->render(), 'Password (removed)'));
});

test('a save that changes nothing records nothing and mails nobody, and still says it was saved', function () {
    Mail::fake();
    signedInSuperAdmin();
    $this->post(route('smtp_settings.update'), secureSmtpForm())->assertSessionHasNoErrors();
    Mail::fake();

    $this->post(route('smtp_settings.update'), secureSmtpForm(['password' => '']))
        ->assertRedirectToRoute('smtp_settings.index')
        ->assertInertiaFlash('success', __('store_admin.smtp_settings_have_been_updated_successfully'));

    expect(AdminAuditLog::query()->count())->toBe(1);
    Mail::assertNothingOutgoing();
});

test('a save whose audit row cannot be written saves nothing', function () {
    Mail::fake();
    signedInSuperAdmin();
    DB::statement('DROP TABLE admin_audit_logs');

    $this->withoutExceptionHandling();
    expect(fn () => $this->post(route('smtp_settings.update'), secureSmtpForm()))->toThrow(QueryException::class);
    expect(secureSmtpRows())->toBe([]);
    Mail::assertNothingOutgoing();
});

// the notification

test('every active Super Admin is mailed at once through the previous SMTP server, with no value or secret', function () {
    Mail::fake();
    $admin = signedInSuperAdmin();
    $other = createStoreAdmin();
    $banned = createStoreAdmin();
    $banned->forceFill(['banned' => 1])->save();
    $customer = createStoreUser('customer');
    $customer->assignRole('Super Admin');
    createStoreStaff('smtp_settings');

    $this->post(route('smtp_settings.update'), secureSmtpForm(['from_address' => '']))->assertSessionHasNoErrors();

    Mail::assertNothingQueued();
    $sent = Mail::sent(SmtpSettingsChangedMail::class);
    expect($sent->map(fn (SmtpSettingsChangedMail $mail) => $mail->to[0]['address'])->sort()->values()->all())
        ->toBe(collect([$admin->email, $other->email])->sort()->values()->all());

    foreach ($sent as $mail) {
        expect($mail->mailer)->toBe(SmtpSettingsChangeNotifier::MAILER);
        $html = $mail->render();
        expect($html)->toContain(e($admin->name))->toContain($admin->email)->toContain(SECURE_IP)
            ->toContain('Host (set)')->toContain('Password (set)')->toContain('SMTP settings were changed');
        foreach ([SECURE_SMTP_SECRET, SECURE_ENV_SECRET, 'smtp.example.com', 'shop-user', 'env-user', 'correct-password'] as $value) {
            expect($html)->not->toContain($value);
        }
    }

    // The runtime mailer with the old server is gone again.
    expect(config('mail.mailers.'.SmtpSettingsChangeNotifier::MAILER))->toBeNull();
});

test('the runtime mailer is built from the settings as they were before the save', function () {
    storeSetting('mail_host', 'old.example.com');
    storeSetting('mail_port', '25');
    storeSetting('mail_username', 'old-user');
    storeSetting('mail_password_encrypted', Crypt::encryptString('old-secret'));
    storeSetting('mail_from_address', 'old@example.com');
    app(StoreMailSettings::class)->forget();
    signedInSuperAdmin();
    $captured = null;
    Mail::shouldReceive('mailer')->andReturnUsing(function (string $name) use (&$captured) {
        $captured = [$name, config('mail.mailers.'.$name)];

        throw new TransportException('stop here');
    });
    Mail::shouldReceive('purge')->zeroOrMoreTimes();
    Mail::shouldReceive('forgetMailers')->zeroOrMoreTimes();

    $this->post(route('smtp_settings.update'), secureSmtpForm(['password' => 'new-secret']))->assertSessionHasNoErrors();

    expect($captured[0])->toBe(SmtpSettingsChangeNotifier::MAILER)
        ->and($captured[1])->toMatchArray([
            'transport' => 'smtp', 'host' => 'old.example.com', 'port' => 25, 'username' => 'old-user', 'password' => 'old-secret', 'timeout' => 10,
        ]);
});

test('a previous mailer other than SMTP is used as it is', function () {
    Mail::fake();
    config(['mail.default' => 'log']);
    signedInSuperAdmin();

    $this->post(route('smtp_settings.update'), secureSmtpForm())->assertSessionHasNoErrors();

    Mail::assertSent(SmtpSettingsChangedMail::class, fn (SmtpSettingsChangedMail $mail) => $mail->mailer === 'log');
});

test('the previous from-address is mailed only when the from address changed, from that address', function () {
    Mail::fake();
    $admin = signedInSuperAdmin();

    $this->post(route('smtp_settings.update'), secureSmtpForm())->assertSessionHasNoErrors();
    Mail::assertSent(SmtpSettingsChangedMail::class, fn (SmtpSettingsChangedMail $mail) => $mail->hasTo('env@example.com')
        && $mail->array['from_address'] === 'env@example.com');
    Mail::assertSent(SmtpSettingsChangedMail::class, fn (SmtpSettingsChangedMail $mail) => $mail->hasTo($admin->email)
        && str_contains($mail->render(), 'From address (set)'));

    Mail::fake();
    $this->post(route('smtp_settings.update'), secureSmtpForm(['from_name' => 'Renamed']))->assertSessionHasNoErrors();
    Mail::assertNotSent(SmtpSettingsChangedMail::class, fn (SmtpSettingsChangedMail $mail) => $mail->hasTo('shop@example.com'));
    Mail::assertSent(SmtpSettingsChangedMail::class, 1);

    Mail::fake();
    $this->post(route('smtp_settings.update'), secureSmtpForm(['from_address' => 'new@example.com']))->assertSessionHasNoErrors();
    Mail::assertSent(SmtpSettingsChangedMail::class, fn (SmtpSettingsChangedMail $mail) => $mail->hasTo('shop@example.com')
        && $mail->array['from_address'] === 'shop@example.com' && $mail->array['changes'] === ['from_address' => 'changed']);
});

test('the previous from-address is not mailed twice when it is a Super Admin\'s own address', function () {
    Mail::fake();
    $admin = signedInSuperAdmin();
    config(['mail.from.address' => strtoupper($admin->email)]);

    $this->post(route('smtp_settings.update'), secureSmtpForm())->assertSessionHasNoErrors();

    Mail::assertSent(SmtpSettingsChangedMail::class, 1);
});

test('a notification that cannot be sent never undoes or fails the save, and logs no value', function () {
    Mail::shouldReceive('mailer')->andThrow(new TransportException('Failed to authenticate on SMTP server with username "env-user"'));
    Mail::shouldReceive('purge')->zeroOrMoreTimes();
    Mail::shouldReceive('forgetMailers')->zeroOrMoreTimes();
    Log::spy();
    signedInSuperAdmin();

    $this->post(route('smtp_settings.update'), secureSmtpForm())
        ->assertRedirectToRoute('smtp_settings.index')
        ->assertInertiaFlash('success', __('store_admin.smtp_settings_have_been_updated_successfully'));

    expect(secureSmtpRows())->toHaveKey('mail_host', 'smtp.example.com')
        ->and(AdminAuditLog::query()->where('action', AdminAuditLog::SMTP_SETTINGS_UPDATED)->count())->toBe(1);
    // Only the exception class: the server's reply quotes the username.
    Log::shouldHaveReceived('warning')->with('The SMTP change notification could not be sent.', ['exception' => TransportException::class]);
});

test('the notification is in the recipient\'s language', function () {
    Mail::fake();
    signedInSuperAdmin();

    $this->post(route('smtp_settings.update'), secureSmtpForm(['from_address' => '']))->assertSessionHasNoErrors();

    foreach (['en' => 'SMTP settings were changed', 'ar' => 'تم تغيير إعدادات SMTP'] as $locale => $text) {
        $mail = Mail::sent(SmtpSettingsChangedMail::class)->first();
        expect($mail->locale($locale)->render())->toContain($text);
    }
});

// the page

test('the page lists the last 10 SMTP saves and refused passwords, newest first, without values or the browser', function () {
    $this->freezeTime();
    $admin = signedInSuperAdmin();
    $gone = AdminAuditLog::factory()->create(['user_id' => 999999, 'user_name' => 'Former Admin', 'user_email' => 'former@example.com', 'created_at' => now()->subDays(2)]);
    $rows = collect(range(1, 8))->map(fn (int $hours) => AdminAuditLog::factory()->create([
        'user_id' => $admin->id, 'changed_fields' => ['host' => 'changed'], 'created_at' => now()->subHours($hours),
    ]));
    $failed = AdminAuditLog::factory()->passwordFailed()->create(['user_id' => $admin->id, 'created_at' => now()]);
    AdminAuditLog::factory()->create(['created_at' => now()->subDays(30)]);
    AdminAuditLog::factory()->create(['action' => 'something_else.updated', 'created_at' => now()->addMinute()]);

    $this->get(route('smtp_settings.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('auditEntries', 10)
        ->where('auditEntries.0.id', $failed->id)
        ->where('auditEntries.0.action', 'smtp_settings.password_failed')
        ->where('auditEntries.1', [
            'id' => $rows[0]->id,
            'action' => 'smtp_settings.updated',
            'changed_fields' => ['host' => 'changed'],
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'user_deleted' => false,
            'user_id' => $admin->id,
            'ip_address' => $rows[0]->ip_address,
            'created_at' => $rows[0]->created_at->toIso8601String(),
        ])
        ->where('auditEntries.9.id', $gone->id)
        ->where('auditEntries.9.user_name', 'Former Admin')
        ->where('auditEntries.9.user_deleted', true)
        ->where('auditEntries', fn ($entries) => collect($entries)->every(fn (array $entry) => ! array_key_exists('user_agent', $entry))));
});
