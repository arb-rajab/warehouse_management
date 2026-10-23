<?php

use App\Mail\SupportMailManager;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProductQuery;
use App\Models\Role;
use App\Models\StoreUser;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\Upload;
use Illuminate\Support\Facades\Mail;

/** The same form setup for valid, hostile and cross-owner writes. */
function sellerTextForm(string $kind, StoreUser $owner): array
{
    $customer = createStoreUser();
    if ($kind === 'ticket') {
        return ['POST', route('seller.support_ticket.store'), route('seller.support_ticket.index'), ['subject' => 'Order help', 'details' => 'Please help'], 'details', Ticket::class, 10000];
    }
    if ($kind === 'reply') {
        $ticket = Ticket::forceCreate(['user_id' => $owner->id, 'subject' => 'Order help', 'code' => '100001', 'details' => 'Please help', 'status' => 'solved', 'viewed' => 1]);

        return ['POST', route('seller.support_ticket.reply_store'), route('seller.support_ticket.show', encrypt($ticket->id)), ['ticket_id' => $ticket->id, 'reply' => 'Please reply'], 'reply', TicketReply::class, 10000];
    }
    if ($kind === 'message') {
        $conversation = Conversation::forceCreate(['sender_id' => $customer->id, 'receiver_id' => $owner->id, 'title' => 'Order help', 'sender_viewed' => 0, 'receiver_viewed' => 0]);

        return ['POST', route('seller.conversations.message_store'), route('seller.conversations.show', encrypt($conversation->id)), ['conversation_id' => $conversation->id, 'message' => 'Please reply'], 'message', Message::class, 65535];
    }
    $query = ProductQuery::forceCreate(['seller_id' => $owner->id, 'customer_id' => $customer->id, 'product_id' => createStoreProduct(['user_id' => $owner->id])->id, 'question' => 'Please reply']);

    return ['PUT', route('seller.product_query.reply', $query->id), route('seller.product_query.show', encrypt($query->id)), ['reply' => 'Please reply'], 'reply', ProductQuery::class, 10000];
}

beforeEach(function () {
    Mail::fake();
});

test('seller text forms mark and require their fields with one legend', function (string $kind) {
    $seller = sellerAccount();
    [$method, $url, $page, $input, $field] = sellerTextForm($kind, $seller);
    $xpath = htmlXPath($this->get($page)->assertOk());
    $form = formPostingTo($xpath, $url);
    $required = $kind === 'ticket' ? ['subject', 'details'] : [$field];
    foreach (formFieldMarks($xpath, $form) as $name => $control) {
        expect($control['mark'])->toBe(in_array($name, $required, true), $name)
            ->and($control['required'])->toBe(in_array($name, $required, true), $name);
    }
    expect($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1);
})->with(['ticket', 'reply', 'message', 'query']);

test('seller text writes accept text boundaries and keep the existing redirect and state changes', function (string $kind) {
    $seller = sellerAccount();
    createStoreUser('admin');
    [$method, $url, $page, $input, $field, $model, $limit] = sellerTextForm($kind, $seller);
    $input[$field] = str_repeat('a', $limit);
    if ($kind === 'ticket') {
        $input['subject'] = str_repeat('é', 255);
    }
    $expectedRedirect = match ($kind) {
        'ticket' => route('seller.support_ticket.index'),
        'query' => route('seller.product_query.index'),
        default => $page,
    };
    $this->from($page)->call($method, $url, $input)->assertRedirect($expectedRedirect)->assertSessionHasNoErrors();
    expect($model::sole()->{$field})->toBe($input[$field]);
    if ($kind === 'ticket') {
        expect(Ticket::sole()->user_id)->toBe($seller->id)->and(flashedMessage())->toBe('Ticket has been sent successfully');
        Mail::assertQueued(SupportMailManager::class);
    } elseif ($kind === 'reply') {
        expect(TicketReply::sole()->user_id)->toBe($seller->id)->and(Ticket::sole()->status)->toBe('pending')->and(Ticket::sole()->viewed)->toBe(0)
            ->and(flashedMessage())->toBe('Reply has been sent successfully');
    } elseif ($kind === 'message') {
        expect(Message::sole()->user_id)->toBe($seller->id)->and(Conversation::sole()->sender_viewed)->toBe(1);
    } else {
        expect(flashedMessage())->toBe('Replied successfully!');
    }
})->with(['ticket', 'reply', 'message', 'query']);

test('seller text writes refuse empty long array and unknown inputs without changing rows', function (string $kind, string $attack) {
    $seller = sellerAccount();
    [$method, $url, $page, $input, $field, $model, $limit] = sellerTextForm($kind, $seller);
    $before = $model::all()->toArray();
    $tickets = Ticket::all()->toArray();
    $conversations = Conversation::all()->toArray();
    if ($attack === 'owner') {
        $field = 'user_id';
        $input[$field] = createStoreUser('seller')->id;
    } elseif ($attack === 'unknown empty') {
        $field = 'unexpected';
        $input[$field] = null;
    } else {
        $input[$field] = match ($attack) {
            'empty' => '', 'long' => str_repeat('a', $limit + 1), 'array' => ['text']
        };
    }
    $bag = $kind === 'ticket' ? 'storeTicket' : 'default';
    $this->call($method, $url, $input)->assertSessionHasErrorsIn($bag, [$field]);
    expect($model::all()->toArray())->toBe($before)->and(Ticket::all()->toArray())->toBe($tickets)->and(Conversation::all()->toArray())->toBe($conversations);
    Mail::assertNothingQueued();
})->with(['ticket', 'reply', 'message', 'query'])->with(['empty', 'long', 'array', 'owner', 'unknown empty']);

test('seller text forms retain old text and show errors underneath it', function (string $kind) {
    $seller = sellerAccount();
    [$method, $url, $page, $input, $field] = sellerTextForm($kind, $seller);
    $input[$field] = 'Keep this text';
    $input['unexpected'] = 1;
    if ($kind === 'ticket') {
        $input['subject'] = '';
    } else {
        $input[$field] = str_repeat('x', 10001 + ($kind === 'message' ? 55535 : 0));
    }
    $this->from($page)->call($method, $url, $input);
    $xpath = htmlXPath($this->get($page)->assertOk());
    $form = formPostingTo($xpath, $url);
    expect($xpath->query('.//textarea[@name="'.$field.'"]', $form)->item(0)->textContent)->toBe($input[$field])
        ->and($xpath->query('.//*[contains(@class,"text-danger")]', $form)->length)->toBeGreaterThan(0);
})->with(['ticket', 'reply', 'message', 'query']);

test('another seller or customer cannot supply the target record even with invalid text', function (string $kind, string $type) {
    $seller = sellerAccount();
    $other = createStoreUser($type);
    [$method, $url, $page, $input, $field, $model] = sellerTextForm($kind, $other);
    $before = $model::all()->toArray();
    $input[$field] = [];
    $response = $this->call($method, $url, $input);
    $kind === 'message' ? $response->assertForbidden() : $response->assertNotFound();
    expect($model::all()->toArray())->toBe($before)->and(Message::count())->toBe(0)->and(TicketReply::count())->toBe(0);
})->with(['reply', 'message', 'query'])->with(['seller', 'customer']);

test('malformed and missing target ids are validation errors and write nothing', function (string $kind, mixed $id) {
    $seller = sellerAccount();
    [$method, $url, $page, $input, $field, $model] = sellerTextForm($kind, $seller);
    $input[$kind === 'reply' ? 'ticket_id' : 'conversation_id'] = $id;
    $this->call($method, $url, $input)->assertSessionHasErrors([$kind === 'reply' ? 'ticket_id' : 'conversation_id']);
    expect($model::count())->toBe(0);
})->with(['reply', 'message'])->with(['missing' => [null], 'text' => ['abc'], 'array' => [[1]]]);

test('ticket photos must be owned images and never another sellers upload', function (string $kind, string $attack) {
    $seller = sellerAccount();
    Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'store']);
    $seller->assignRole(Role::SUPER_ADMIN);
    [$method, $url, $page, $input, $field, $model] = sellerTextForm($kind, $seller);
    $photo = Upload::factory()->create(['user_id' => $attack === 'foreign' ? createStoreUser('seller')->id : $seller->id, 'type' => $attack === 'document' ? 'document' : 'image']);
    $input['attachments'] = match ($attack) {
        'array' => [$photo->id], 'duplicate' => $photo->id.','.$photo->id, 'missing' => '999999', default => (string) $photo->id
    };
    $this->call($method, $url, $input)->assertSessionHasErrorsIn($kind === 'ticket' ? 'storeTicket' : 'default', ['attachments']);
    expect($model::count())->toBe(0);
})->with(['ticket', 'reply'])->with(['foreign', 'document', 'array', 'duplicate', 'missing']);

test('ticket create refuses subject limits and wrong types', function (mixed $subject) {
    $seller = sellerAccount();
    $this->post(route('seller.support_ticket.store'), ['subject' => $subject, 'details' => 'Details'])->assertSessionHasErrorsIn('storeTicket', ['subject']);
    expect(Ticket::count())->toBe(0);
})->with(['empty' => [''], 'long' => [str_repeat('é', 256)], 'array' => [['text']]]);

test('seller message honors the MySQL text byte boundary for multibyte text', function (bool $over) {
    $seller = sellerAccount();
    [$method, $url, $page, $input] = sellerTextForm('message', $seller);
    $input['message'] = $over ? str_repeat('é', 32768) : str_repeat('é', 32767).'a';
    $response = $this->post($url, $input);
    if ($over) {
        $response->assertSessionHasErrors('message');
        expect(Message::count())->toBe(0);
    } else {
        $response->assertSessionHasNoErrors();
        expect(strlen(Message::sole()->message))->toBe(65535);
    }
})->with([true, false]);

test('seller text markup keeps its existing storage and escaping', function (string $kind) {
    $seller = sellerAccount();
    [$method, $url, $page, $input, $field, $model] = sellerTextForm($kind, $seller);
    $input[$field] = '<p>Hello <i>there</i></p>';
    $this->from($page)->call($method, $url, $input)->assertSessionHasNoErrors();
    expect($model::sole()->{$field})->toBe($input[$field]);
    if ($kind === 'ticket') {
        $page = route('seller.support_ticket.show', encrypt(Ticket::sole()->id));
    }
    if ($kind !== 'query') {
        $this->get($page)->assertOk()->assertSee($kind === 'message' ? '&lt;p&gt;Hello &lt;i&gt;there&lt;/i&gt;&lt;/p&gt;' : '<p>Hello <i>there</i></p>', false);
    }
})->with(['ticket', 'reply', 'message', 'query']);

test('guests and customers cannot submit seller text writes', function (string $kind, bool $guest) {
    $owner = createStoreUser('seller');
    [$method, $url, $page, $input, $field, $model] = sellerTextForm($kind, $owner);
    $before = $model::all()->toArray();
    if (! $guest) {
        actingAsStoreUser(createStoreUser());
    }
    $this->call($method, $url, $input)->assertNotFound();
    expect($model::all()->toArray())->toBe($before);
})->with(['ticket', 'reply', 'message', 'query'])->with([true, false]);

test('ticket writes accept only the callers own comma separated photo ids', function (string $kind) {
    $seller = sellerAccount();
    [$method, $url, $page, $input, $field, $model] = sellerTextForm($kind, $seller);
    $photos = Upload::factory()->count(2)->create(['user_id' => $seller->id, 'type' => 'image'])->pluck('id')->implode(',');
    $this->call($method, $url, [...$input, 'attachments' => $photos])->assertSessionHasNoErrors();
    expect($model::sole()->files)->toBe($photos);
})->with(['ticket', 'reply']);

test('seller text writes refuse their removed or unshown controls', function (string $kind) {
    $seller = sellerAccount();
    [$method, $url, $page, $input, $field, $model] = sellerTextForm($kind, $seller);
    $before = $model::all()->toArray();
    $unexpected = match ($kind) {
        'ticket' => 'status', 'reply' => 'user_id', 'message' => 'attachments', 'query' => 'conversation_id'
    };
    $this->call($method, $url, [...$input, $unexpected => 123])->assertSessionHasErrorsIn($kind === 'ticket' ? 'storeTicket' : 'default', [$unexpected]);
    expect($model::all()->toArray())->toBe($before);
})->with(['ticket', 'reply', 'message', 'query']);

test('seller text forms render safely after arrays are refused', function (string $kind) {
    $seller = sellerAccount();
    [$method, $url, $page, $input, $field] = sellerTextForm($kind, $seller);
    $this->from($page)->call($method, $url, [...$input, $field => ['bad']]);
    $this->get($page)->assertOk();
})->with(['ticket', 'reply', 'message', 'query']);

test('valid text cannot write to a second sellers record', function (string $kind) {
    sellerAccount();
    $other = createStoreUser('seller');
    [$method, $url, $page, $input, $field, $model] = sellerTextForm($kind, $other);
    $before = $model::all()->toArray();
    $response = $this->call($method, $url, $input);
    $kind === 'message' ? $response->assertForbidden() : $response->assertNotFound();
    expect($model::all()->toArray())->toBe($before);
})->with(['reply', 'message', 'query']);
