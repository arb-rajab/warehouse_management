<?php

use App\Mail\SupportMailManager;
use App\Models\StoreUser;
use App\Models\Ticket;
use App\Models\Upload;
use Illuminate\Support\Facades\Mail;

/**
 * "Create a Ticket": the modal on the customer's support ticket page
 * (shop.frontend.user.support_ticket.index), posting to
 * SupportTicketController::store (support_ticket.store, signed-in accounts).
 * StoreSupportTicketRequest requires the subject (tickets.subject,
 * varchar(255)) and the details (the back office's limit for support text,
 * 10,000 characters); the photos are optional, comma-separated image uploads
 * of the customer's own, into its own error bag (.ai/archive/store-backoffice/changes-20261021.md
 * §48).
 */
const SUPPORT_TICKET_FIELDS = ['subject', 'details'];

/**
 * @return array<string, mixed>
 */
function supportTicketPayload(array $overrides = []): array
{
    return ['subject' => 'My order is late', 'details' => 'Order 1234 has not arrived.', ...$overrides];
}

function customerImageUpload(StoreUser $owner): int
{
    return Upload::factory()->create(['type' => 'image', 'user_id' => $owner->id])->id;
}

beforeEach(function () {
    Mail::fake();
});

describe('the form', function () {
    test('marks and requires the subject and the details, not the photos, under the legend', function () {
        actingAsStoreUser(createStoreUser());

        $xpath = htmlXPath($this->get(route('support_ticket.index'))->assertOk());
        $form = formPostingTo($xpath, '/temp/support_ticket');
        $required = ['required' => true, 'mark' => true, 'optional' => false];

        expect(formFieldMarks($xpath, $form))->toBe([
            'subject' => ['tag' => 'input', ...$required],
            'details' => ['tag' => 'textarea', ...$required],
        ])
            ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
            ->and($xpath->query('.//input[@name="subject"]', $form)->item(0)->getAttribute('maxlength'))->toBe('255')
            ->and($xpath->query('.//textarea[@name="details"]', $form)->item(0)->getAttribute('maxlength'))->toBe('10000')
            ->and($xpath->query('.//input[@name="attachments"]', $form)->item(0)->hasAttribute('required'))->toBeFalse();

        foreach (SUPPORT_TICKET_FIELDS as $name) {
            $id = $xpath->query('.//*[@name="'.$name.'"]', $form)->item(0)->getAttribute('id');
            expect($id)->not->toBe('')
                ->and($xpath->query('.//label[@for="'.$id.'"]', $form)->length)->toBe(1, "{$name} has a label");
        }
    });

    test('a refused ticket reopens the modal with the input kept and each error under its field', function () {
        actingAsStoreUser(createStoreUser());
        $index = route('support_ticket.index');

        $this->from($index)->post('/temp/support_ticket', supportTicketPayload(['subject' => '', 'details' => '<b>Still</b> late']))
            ->assertRedirect($index);

        $response = $this->get($index)->assertOk();
        $form = formPostingTo($xpath = htmlXPath($response), '/temp/support_ticket');

        expect($xpath->query('.//input[@name="subject"]', $form)->item(0)->getAttribute('class'))->toContain('is-invalid')
            ->and(trim($xpath->query('.//input[@name="subject"]/following-sibling::*[@role="alert"]', $form)->item(0)?->textContent ?? ''))->toBe('The subject field is required.')
            ->and(trim($xpath->query('.//textarea[@name="details"]', $form)->item(0)->textContent))->toBe('<b>Still</b> late')
            ->and($response->getContent())->toContain("$(function () { $('#ticket_modal').modal('show'); });");
    });

    test('a fresh page does not open the modal', function () {
        actingAsStoreUser(createStoreUser());

        $this->get(route('support_ticket.index'))->assertOk()->assertDontSee("$(function () { $('#ticket_modal').modal('show'); });", false);
    });
});

describe('opening a ticket', function () {
    test('saves the ticket as the caller\'s, mails the admin and goes to the ticket list, as before', function () {
        $customer = actingAsStoreUser(createStoreUser());
        $admin = createStoreUser('admin', 'admin@example.com');
        $photos = customerImageUpload($customer).','.customerImageUpload($customer);

        $this->post('/temp/support_ticket', supportTicketPayload(['attachments' => $photos]))
            ->assertRedirect(route('support_ticket.index'))
            ->assertSessionHasNoErrors();

        expect(Ticket::sole()->only(['user_id', 'subject', 'details', 'files', 'status']))->toBe([
            'user_id' => $customer->id, 'subject' => 'My order is late', 'details' => 'Order 1234 has not arrived.', 'files' => $photos, 'status' => 'pending',
        ])
            ->and(flashedMessage())->toBe('Ticket has been sent successfully');
        Mail::assertQueued(SupportMailManager::class, fn (SupportMailManager $mail) => $mail->hasTo($admin->email));
    });

    test('takes a ticket without photos, and fields up to their limits', function () {
        actingAsStoreUser(createStoreUser());

        $this->post('/temp/support_ticket', supportTicketPayload(['subject' => str_repeat('é', 255), 'details' => str_repeat('a', 10000), 'attachments' => '']))
            ->assertSessionHasNoErrors();

        expect(mb_strlen(Ticket::sole()->subject))->toBe(255)
            ->and(Ticket::sole()->files)->toBeNull();
    });

    test('a missing field is refused with its own error and writes nothing', function (string $field) {
        actingAsStoreUser(createStoreUser());
        $payload = supportTicketPayload();
        unset($payload[$field]);

        $this->post('/temp/support_ticket', $payload)->assertSessionHasErrorsIn('storeTicket', [$field]);

        expect(Ticket::count())->toBe(0);
        Mail::assertNothingQueued();
    })->with(SUPPORT_TICKET_FIELDS);

    test('hostile input is refused and writes nothing', function (Closure $input, string $field) {
        $customer = actingAsStoreUser(createStoreUser());

        $this->post('/temp/support_ticket', supportTicketPayload($input($customer)))->assertSessionHasErrorsIn('storeTicket', [$field]);

        expect(Ticket::count())->toBe(0);
    })->with([
        'a subject longer than tickets.subject' => [fn () => ['subject' => str_repeat('a', 256)], 'subject'],
        'a multibyte subject longer than tickets.subject' => [fn () => ['subject' => str_repeat('ü', 256)], 'subject'],
        'details longer than the support limit' => [fn () => ['details' => str_repeat('a', 10001)], 'details'],
        'a subject that is an array' => [fn () => ['subject' => ['a']], 'subject'],
        'details that are an array' => [fn () => ['details' => ['a']], 'details'],
        'photos that are an array' => [fn (StoreUser $customer) => ['attachments' => [customerImageUpload($customer)]], 'attachments'],
        'photos that are not upload ids' => [fn () => ['attachments' => '1,abc'], 'attachments'],
        'a photo that does not exist' => [fn () => ['attachments' => '999999'], 'attachments'],
        'another account\'s photo' => [fn (StoreUser $customer) => ['attachments' => customerImageUpload($customer).','.customerImageUpload(createStoreUser())], 'attachments'],
        'an upload that is not an image' => [fn (StoreUser $customer) => ['attachments' => (string) Upload::factory()->create(['type' => 'document', 'user_id' => $customer->id])->id], 'attachments'],
        'the same photo twice' => [fn (StoreUser $customer) => ['attachments' => ($id = customerImageUpload($customer)).','.$id], 'attachments'],
    ]);

    test('keeps markup as sent, and the ticket page prints it through clean_html as before', function () {
        actingAsStoreUser(createStoreUser());

        $this->post('/temp/support_ticket', supportTicketPayload(['subject' => '<b>Late</b> & "lost"', 'details' => '<p>Hello <i>there</i></p>']))->assertSessionHasNoErrors();

        $ticket = Ticket::sole();
        expect($ticket->only(['subject', 'details']))->toBe(['subject' => '<b>Late</b> & "lost"', 'details' => '<p>Hello <i>there</i></p>']);

        $this->get(route('support_ticket.show', encrypt($ticket->id)))->assertOk()
            ->assertSee('&lt;b&gt;Late&lt;/b&gt; &amp; &quot;lost&quot;', false)
            ->assertSee('<p>Hello <i>there</i></p>', false);
    });

    test('ignores an owner or a status it is sent', function () {
        $customer = actingAsStoreUser(createStoreUser());
        $other = createStoreUser('customer', 'other@example.com');

        $this->post('/temp/support_ticket', supportTicketPayload(['user_id' => $other->id, 'status' => 'solved', 'code' => 1]))->assertSessionHasNoErrors();

        expect(Ticket::sole()->only(['user_id', 'status']))->toBe(['user_id' => $customer->id, 'status' => 'pending']);
    });

    test('a guest is sent to sign in and nothing is written', function () {
        $this->post('/temp/support_ticket', supportTicketPayload())->assertRedirect(route('user.login'));

        expect(Ticket::count())->toBe(0);
        Mail::assertNothingQueued();
    });
});
