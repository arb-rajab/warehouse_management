<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Mail\EmailManager;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function adminNewsletterForm(array $overrides = []): array
{
    $user = createStoreUser('customer');

    return ['user_emails' => [$user->email], 'subject' => 'مرحبا', 'content' => '<p><strong>Welcome</strong></p>', ...$overrides];
}

beforeEach(function () {
    Mail::fake();
});

test('the newsletter screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('send_newsletter'));
    $this->get(route('newsletters.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Marketing/Newsletters/Index')
    );
});

test('the newsletter screen sends only the SMTP state and leaves recipients to the bounded search', function (?string $username, bool $configured) {
    $customer = createStoreUser('customer');
    Subscriber::forceCreate(['email' => 'reader@example.test']);
    actingAsStoreUser(createStoreStaff('send_newsletter'));
    config(['mail.mailers.smtp.username' => $username]);
    $response = $this->get(route('newsletters.index'))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Marketing/Newsletters/Index')
        ->where('smtpConfigured', $configured)
        ->missing('users')->missing('subscribers'));
    // The Blade screen printed every user and subscriber email into the page.
    $response->assertDontSee($customer->email)->assertDontSee('reader@example.test');
    Mail::assertNothingQueued();
})->with(['unconfigured' => [null, false], 'configured' => ['mailer-user', true]]);

test('WMS sessions cannot open or use the newsletter', function () {
    config(['mail.mailers.smtp.username' => 'configured']);
    $this->actingAs(User::factory()->create(), 'web');
    $this->get(route('newsletters.index'))->assertRedirect(route('shop.login'));
    $this->get(route('newsletters.recipients', ['kind' => 'users']))->assertRedirect(route('shop.login'));
    $this->post(route('newsletters.send'), adminNewsletterForm())->assertRedirect(route('shop.login'));
    Mail::assertNothingQueued();
});

test('newsletter recipient searches are narrow bounded and isolated by recipient kind', function () {
    $user = createStoreUser('customer');
    $user->forceFill(['email' => 'target@example.test'])->save();
    createStoreUser('customer');
    Subscriber::forceCreate(['email' => 'subscriber@example.test']);
    foreach (range(1, 35) as $number) {
        Subscriber::forceCreate(['email' => sprintf('reader%02d@example.test', $number)]);
    }
    actingAsStoreUser(createStoreStaff('send_newsletter'));
    $this->getJson(route('newsletters.recipients', ['kind' => 'users', 'q' => 'target']))->assertOk()->assertExactJson(['target@example.test']);
    $this->getJson(route('newsletters.recipients', ['kind' => 'subscribers', 'q' => 'subscriber']))->assertOk()->assertExactJson(['subscriber@example.test']);
    expect($this->getJson(route('newsletters.recipients', ['kind' => 'subscribers']))->json())->toHaveCount(30);
    $this->getJson(route('newsletters.recipients', ['kind' => 'other']))->assertUnprocessable()->assertJsonValidationErrors('kind');
    $this->getJson(route('newsletters.recipients', ['kind' => 'users', 'q' => str_repeat('x', 256)]))->assertUnprocessable()->assertJsonValidationErrors('q');
});

test('newsletters queue only selected persisted recipients once and sanitize HTML in the unchanged Blade email', function () {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    config(['mail.mailers.smtp.username' => 'configured']);
    $user = createStoreUser('customer');
    Subscriber::forceCreate(['email' => $user->email]);
    Subscriber::forceCreate(['email' => 'selected@example.test']);
    Subscriber::forceCreate(['email' => 'unrelated@example.test']);
    actingAsStoreUser(createStoreStaff('send_newsletter'));
    $this->post(route('newsletters.send'), [
        'user_emails' => [$user->email], 'subscriber_emails' => [$user->email, 'selected@example.test'],
        'subject' => 'عروض', 'content' => '<p><strong>Safe</strong><script>alert(1)</script><a href="javascript:alert(1)">link</a></p>',
    ])->assertRedirect(route('shop.admin.dashboard'));
    Mail::assertQueuedCount(2);
    Mail::assertQueued(EmailManager::class, function (EmailManager $mail) use ($user): bool {
        expect($mail->array['subject'])->toBe('عروض')->and($mail->array['view'])->toBe('shop.emails.newsletter');
        $rendered = view('shop.emails.newsletter', ['array' => $mail->array])->render();
        expect($rendered)->toContain('<strong>Safe</strong>')->not->toContain('<script')->not->toContain('javascript:');

        return $mail->hasTo($user->email);
    });
    Mail::assertNotQueued(EmailManager::class, fn (EmailManager $mail) => $mail->hasTo('unrelated@example.test'));
});

test('a newsletter refuses unconfigured SMTP without queueing', function () {
    actingAsStoreUser(createStoreStaff('send_newsletter'));
    config(['mail.mailers.smtp.username' => null]);
    $this->postJson(route('newsletters.send'), adminNewsletterForm())->assertUnprocessable()->assertJsonValidationErrors('smtp');
    Mail::assertNothingQueued();
});

test('newsletter writes validate recipients subject and safe content before queueing', function (array $overrides, string $field) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    config(['mail.mailers.smtp.username' => 'configured']);
    actingAsStoreUser(createStoreStaff('send_newsletter'));
    $this->postJson(route('newsletters.send'), adminNewsletterForm($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
    Mail::assertNothingQueued();
})->with([
    'unknown user' => [['user_emails' => ['unknown@example.test']], 'user_emails.0'],
    'unknown subscriber' => [['subscriber_emails' => ['unknown@example.test']], 'subscriber_emails.0'],
    'no recipients' => [['user_emails' => []], 'user_emails'],
    'subject required' => [['subject' => ''], 'subject'],
    'subject width' => [['subject' => str_repeat('x', 256)], 'subject'],
    'content required' => [['content' => ''], 'content'],
    'content width' => [['content' => str_repeat('x', 100001)], 'content'],
    'unsafe only content' => [['content' => '<script>alert(1)</script>'], 'content'],
]);

test('the SMTP test validates its address and queues the existing test message', function () {
    actingAsStoreUser(createStoreStaff('send_newsletter', 'smtp_settings'));
    $this->postJson(route('test.smtp'), ['email' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors('email');
    Mail::assertNothingQueued();
    $this->post(route('test.smtp'), ['email' => 'test@example.test'])->assertRedirect(route('smtp_settings.index'));
    Mail::assertQueued(EmailManager::class, fn (EmailManager $mail) => $mail->hasTo('test@example.test') && $mail->array['subject'] === 'SMTP Test' && $mail->array['content'] === 'This is a test email.');
});

foreach (['index', 'recipients', 'send', 'testEmail'] as $action) {
    test("newsletter {$action} refuses unauthorized callers and queues nothing", function (string $caller) use ($action) {
        $data = adminNewsletterForm();
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'index' => $this->get(route('newsletters.index')),
            'recipients' => $this->get(route('newsletters.recipients', ['kind' => 'users'])),
            'send' => $this->post(route('newsletters.send'), $data),
            'testEmail' => $this->post(route('test.smtp'), ['email' => 'test@example.test']),
        };
        assertStoreCallerRefused($response, $caller);
        Mail::assertNothingQueued();
    })->with('refused store admin callers');
}
