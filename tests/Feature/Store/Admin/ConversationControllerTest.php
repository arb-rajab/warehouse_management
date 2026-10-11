<?php

use App\Models\BusinessSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Permission;
use App\Models\StoreUser;
use Inertia\Testing\AssertableInertia as Assert;

function supportConversation(?StoreUser $receiver = null): Conversation
{
    return Conversation::forceCreate([
        'sender_id' => createStoreUser()->id,
        'receiver_id' => ($receiver ?? createStoreUser('admin'))->id,
        'title' => 'Delivery question', 'sender_viewed' => 1, 'receiver_viewed' => 0,
    ]);
}

beforeEach(function () {
    BusinessSetting::forceCreate(['type' => 'conversation_system', 'value' => '1']);
});

test('the conversation list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('view_all_product_conversations'));
    $this->get(route('conversations.admin_index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Support/Conversations/Index')
    );
});

test('the conversation list returns a narrow complete payload and pages deterministically', function () {
    actingAsStoreUser(createStoreStaff('view_all_product_conversations'));
    $conversation = supportConversation();
    $this->get(route('conversations.admin_index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('filters', ['per_page' => 20])->where('enabled', true)
            ->has('conversations.data', 1)
            ->has('conversations.data.0', fn (Assert $row) => $row
                ->where('id', $conversation->id)->where('title', 'Delivery question')
                ->where('sender', ['id' => $conversation->sender_id, 'name' => 'Customer Tester'])
                ->where('receiver', ['id' => $conversation->receiver_id, 'name' => 'Admin Tester'])
                ->where('sender_viewed', true)->where('receiver_viewed', false)
                ->where('created_at', $conversation->created_at->toIso8601String()))
    );
    foreach (range(1, 22) as $number) {
        supportConversation();
    }
    $this->get(route('conversations.admin_index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('conversations.data', 20)->where('conversations.meta.total', 23)
    );
    $this->get(route('conversations.admin_index', ['page' => 2]))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('conversations.data', 3)
    );
    $this->get(route('conversations.admin_index', ['per_page' => 10]))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('conversations.data', 10)->where('filters.per_page', 10)
    );
    $this->get(route('conversations.admin_index', ['per_page' => 7]))->assertSessionHasErrors('per_page');
});

test('a disabled conversation system renders an empty list without a redirect loop', function () {
    BusinessSetting::query()->where('type', 'conversation_system')->update(['value' => '0']);
    supportConversation();
    actingAsStoreUser(createStoreStaff('view_all_product_conversations'));
    $this->get(route('conversations.admin_index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('enabled', false)->has('conversations.data', 0)
    );
});

test('the conversation screen can be rendered with paginated messages and no unrelated thread', function () {
    $conversation = supportConversation();
    $noise = supportConversation();
    $staff = actingAsStoreUser(createStoreStaff('view_all_product_conversations'));
    $message = Message::forceCreate(['conversation_id' => $conversation->id, 'user_id' => $staff->id, 'message' => '<img src=x onerror=alert(1)>']);
    Message::forceCreate(['conversation_id' => $noise->id, 'user_id' => $staff->id, 'message' => 'unrelated']);
    $this->get(route('conversations.admin_show', $conversation))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Support/Conversations/Show')->where('canReply', true)
            ->has('messages.data', 1)
            ->has('messages.data.0', fn (Assert $row) => $row->where('id', $message->id)
                ->where('message', '<img src=x onerror=alert(1)>')
                ->where('user', ['id' => $staff->id, 'name' => $staff->name])
                ->where('created_at', $message->created_at->toIso8601String()))
    );
    foreach (range(1, 22) as $number) {
        Message::forceCreate(['conversation_id' => $conversation->id, 'user_id' => $staff->id, 'message' => "Message $number"]);
    }
    $this->get(route('conversations.admin_show', $conversation))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('messages.data', 20)->where('messages.meta.total', 23)
    );
    $this->get(route('conversations.admin_show', [$conversation, 'page' => 2]))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('messages.data', 3)
    );
    $this->get(route('conversations.admin_show', [$conversation, 'per_page' => 10]))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('messages.data', 10)->where('filters.per_page', 10)
    );
    $this->get(route('conversations.admin_show', [$conversation, 'per_page' => 7]))->assertSessionHasErrors('per_page');
    expect($conversation->fresh()->receiver_viewed)->toBe(0);
});

test('viewing a conversation marks only the current participant as viewed', function () {
    $owner = createStoreUser('admin');
    $owner->givePermissionTo(Permission::findOrCreate('view_all_product_conversations', 'store'));
    $conversation = supportConversation($owner);
    actingAsStoreUser($owner);
    $this->get(route('conversations.admin_show', $conversation))->assertOk();
    expect($conversation->fresh()->receiver_viewed)->toBe(1);
});

test('conversation replies use the current store account, sanitize HTML, and preserve legacy viewed flags', function () {
    config(['waf.enabled' => false]);
    $staff = actingAsStoreUser(createStoreStaff('reply_to_product_conversations'));
    $conversation = supportConversation();
    $noise = supportConversation();
    $this->post(route('conversations.admin_reply', $conversation), [
        'message' => '<script>bad</script><b>Help</b>', 'user_id' => $conversation->sender_id, 'conversation_id' => $noise->id,
    ])->assertRedirect(route('conversations.admin_show', $conversation));
    expect(Message::query()->first()->only(['conversation_id', 'user_id', 'message']))->toBe([
        'conversation_id' => $conversation->id, 'user_id' => $staff->id, 'message' => '<b>Help</b>',
    ])->and($conversation->fresh()->receiver_viewed)->toBe(0)
        ->and($noise->messages()->count())->toBe(0);
});

test('conversation replies require the write permission even when the caller can read', function () {
    $conversation = supportConversation();
    actingAsStoreUser(createStoreStaff('view_all_product_conversations'));
    $this->post(route('conversations.admin_reply', $conversation), ['message' => 'forged'])->assertForbidden();
    expect(Message::count())->toBe(0)->and($conversation->fresh()->receiver_viewed)->toBe(0);
});

test('conversation replies refuse seller inboxes even for a super admin', function () {
    $conversation = supportConversation(createStoreUser('seller'));
    actingAsStoreUser(createStoreAdmin());
    $this->get(route('conversations.admin_show', $conversation))->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('canReply', false)
    );
    $this->post(route('conversations.admin_reply', $conversation), ['message' => 'forged'])->assertForbidden();
    expect(Message::count())->toBe(0);
});

test('conversation replies validate text before changing anything', function (mixed $text) {
    config(['waf.enabled' => false]);
    $conversation = supportConversation();
    actingAsStoreUser(createStoreStaff('reply_to_product_conversations'));
    $this->post(route('conversations.admin_reply', $conversation), ['message' => $text])->assertSessionHasErrors('message');
    expect(Message::count())->toBe(0);
})->with(['missing' => [null], 'array' => [['bad']], 'too long' => [str_repeat('x', 10001)]]);

test('deleting a conversation removes only that thread and keeps list query parameters', function () {
    $conversation = supportConversation();
    $noise = supportConversation();
    Message::forceCreate(['conversation_id' => $conversation->id, 'user_id' => $conversation->sender_id, 'message' => 'delete']);
    Message::forceCreate(['conversation_id' => $noise->id, 'user_id' => $noise->sender_id, 'message' => 'keep']);
    actingAsStoreUser(createStoreStaff('delete_product_conversations'));
    $this->delete(route('conversations.admin_destroy', [$conversation, 'page' => 2, 'per_page' => 10]))
        ->assertRedirect(route('conversations.admin_index', ['page' => 2, 'per_page' => 10]));
    expect(Conversation::query()->pluck('id')->all())->toBe([$noise->id])
        ->and(Message::query()->pluck('message')->all())->toBe(['keep']);
});

test('support conversation reads refuse unauthorized callers', function (string $caller, string $route) {
    $conversation = supportConversation();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route($route, $route === 'conversations.admin_index' ? [] : $conversation)), $caller);
    expect($conversation->fresh()->receiver_viewed)->toBe(0);
})->with('refused store admin callers')->with(['conversations.admin_index', 'conversations.admin_show']);

test('support conversation replies refuse unauthorized callers without changing a thread', function (string $caller) {
    $conversation = supportConversation();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post(route('conversations.admin_reply', $conversation), ['message' => 'forged']), $caller);
    expect(Message::count())->toBe(0)->and($conversation->fresh()->receiver_viewed)->toBe(0);
})->with('refused store admin callers');

test('support conversation deletes refuse unauthorized callers without deleting a thread', function (string $caller) {
    $conversation = supportConversation();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete(route('conversations.admin_destroy', $conversation)), $caller);
    expect(Conversation::count())->toBe(1);
})->with('refused store admin callers');

test('missing support conversations return 404', function (string $method, string $route) {
    actingAsStoreUser(createStoreAdmin());
    $this->{$method}(route($route, 999999), ['message' => 'valid'])->assertNotFound();
})->with([['get', 'conversations.admin_show'], ['post', 'conversations.admin_reply'], ['delete', 'conversations.admin_destroy']]);

test('a WMS session alone cannot access or write store conversations', function (string $method, string $route) {
    $conversation = supportConversation();
    actingAsAdmin();
    $this->{$method}(route($route, $route === 'conversations.admin_index' ? [] : $conversation), ['message' => 'forged'])->assertRedirect(route('shop.login'));
    expect(Message::count())->toBe(0)->and(Conversation::count())->toBe(1)->and($conversation->fresh()->receiver_viewed)->toBe(0);
})->with([['get', 'conversations.admin_index'], ['get', 'conversations.admin_show'], ['post', 'conversations.admin_reply'], ['delete', 'conversations.admin_destroy']]);

test('the request firewall still refuses malicious conversation replies before writing', function () {
    $conversation = supportConversation();
    actingAsStoreUser(createStoreStaff('reply_to_product_conversations'));
    $this->post(route('conversations.admin_reply', $conversation), ['message' => '<script>attack</script>'])->assertForbidden();
    expect(Message::count())->toBe(0);
});

test('the newest reply stays on the default history page and older messages remain paginated', function () {
    $staff = actingAsStoreUser(createStoreStaff('view_all_product_conversations', 'reply_to_product_conversations'));
    $conversation = supportConversation();
    $messages = collect(range(1, 23))->map(fn ($n) => Message::forceCreate(['conversation_id' => $conversation->id, 'user_id' => $staff->id, 'message' => "History $n"]));
    $this->post(route('conversations.admin_reply', $conversation), ['message' => 'Latest reply'])->assertRedirect(route('conversations.admin_show', $conversation));
    $this->get(route('conversations.admin_show', $conversation))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('messages.data', 20)->where('messages.data.0.id', $messages[4]->id)->where('messages.data.19.message', 'Latest reply'));
    $this->get(route('conversations.admin_show', [$conversation, 'page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('messages.data', 4)->where('messages.data.0.id', $messages[0]->id)->where('messages.data.3.id', $messages[3]->id));
});
