<?php

use App\Mail\ConversationMailManager;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/**
 * "Any query about this product": the chat modal on the product page
 * (shop.frontend.product_details) and on the digital product page
 * (shop.frontend.digital_product_details, which no route renders), posting to
 * ConversationController::store (conversations.store, signed-in only).
 * StoreConversationRequest requires a visible product (published and
 * approved), a title within conversations.title (varchar(1000)) and a message
 * within messages.message (text), into its own error bag
 * (.ai/archive/store-backoffice/changes-20261021.md §48).
 */
const CONVERSATION_FIELDS = ['title', 'message'];

/**
 * @return array<string, mixed>
 */
function conversationPayload(Product $product, array $overrides = []): array
{
    return ['product_id' => $product->id, 'title' => 'Question about '.$product->name, 'message' => 'Is this in stock?', ...$overrides];
}

function conversationProduct(array $attributes = []): Product
{
    $seller = createStoreUser('seller', 'seller-'.Str::random(6).'@example.com');

    return createStoreProduct(['user_id' => $seller->id, 'approved' => 1, ...$attributes]);
}

function productPageWithConversationForm(Product $product): DOMXPath
{
    return htmlXPath(test()->get('/temp/product/'.$product->slug)->assertOk());
}

function expectNoConversation(): void
{
    expect(Conversation::count())->toBe(0)->and(Message::count())->toBe(0);
}

beforeEach(function () {
    seedStoreCurrency();
    Mail::fake();
});

describe('the forms', function () {
    test('mark and require the title and the message, under the legend, on both product pages', function (string $page) {
        $customer = actingAsStoreUser(createStoreUser());
        $product = conversationProduct(['digital' => $page === 'digital' ? 1 : 0]);
        Route::middleware('web')->get('/temp/__digital/{slug}', fn (string $slug) => view('shop.frontend.digital_product_details', [
            'detailedProduct' => Product::with('stocks', 'reviews', 'brand', 'user')->where('slug', $slug)->firstOrFail(),
            'product_queries' => collect(), 'total_query' => 0, 'reviews' => collect(), 'review_status' => 0, 'variations' => null,
        ]));

        $xpath = htmlXPath($this->get(($page === 'digital' ? '/temp/__digital/' : '/temp/product/').$product->slug)->assertOk());
        $form = formPostingTo($xpath, '/temp/conversations');
        $required = ['required' => true, 'mark' => true, 'optional' => false];

        expect(formFieldMarks($xpath, $form))->toBe([
            'title' => ['tag' => 'input', ...$required],
            'message' => ['tag' => 'textarea', ...$required],
        ])
            ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
            ->and($xpath->query('.//input[@name="title"]', $form)->item(0)->getAttribute('maxlength'))->toBe('1000')
            ->and($xpath->query('.//textarea[@name="message"]', $form)->item(0)->getAttribute('maxlength'))->toBe('65535')
            ->and($xpath->query('.//input[@name="product_id"]', $form)->item(0)->getAttribute('value'))->toBe((string) $product->id);

        foreach (CONVERSATION_FIELDS as $name) {
            $id = $xpath->query('.//*[@name="'.$name.'"]', $form)->item(0)->getAttribute('id');
            expect($id)->not->toBe('')
                ->and($xpath->query('.//label[@for="'.$id.'"]', $form)->length)->toBe(1, "{$name} has a label");
        }
        expect($customer->exists)->toBeTrue();
    })->with(['product', 'digital']);

    test('a refused question reopens the modal with the input kept and each error under its field', function () {
        actingAsStoreUser(createStoreUser());
        $product = conversationProduct();
        $url = '/temp/product/'.$product->slug;

        $this->from($url)->post('/temp/conversations', conversationPayload($product, ['title' => str_repeat('a', 1001), 'message' => '<b>Still</b> there?']))
            ->assertRedirect($url);

        $response = $this->get($url)->assertOk();
        $form = formPostingTo($xpath = htmlXPath($response), '/temp/conversations');

        expect($xpath->query('.//input[@name="title"]', $form)->item(0)->getAttribute('class'))->toContain('is-invalid')
            ->and(trim($xpath->query('.//input[@name="title"]/following-sibling::*[@role="alert"]', $form)->item(0)?->textContent ?? ''))
            ->toBe('The title field must not be greater than 1000 characters.')
            ->and($xpath->query('.//input[@name="title"]', $form)->item(0)->getAttribute('value'))->toBe(str_repeat('a', 1001))
            ->and(trim($xpath->query('.//textarea[@name="message"]', $form)->item(0)->textContent))->toBe('<b>Still</b> there?')
            ->and($response->getContent())->toContain("$('#chat_modal').modal('show');")
            // The product page's other forms read the default bag and stay clean.
            ->and($xpath->query('//form[not(contains(@action, "/temp/conversations"))]//*[contains(@class, "is-invalid")]')->length)->toBe(0);
    });

    test('a fresh page keeps its prefilled title and message and does not open the modal', function () {
        actingAsStoreUser(createStoreUser());
        $product = conversationProduct();

        $response = $this->get('/temp/product/'.$product->slug)->assertOk();
        $form = formPostingTo($xpath = htmlXPath($response), '/temp/conversations');

        expect($xpath->query('.//input[@name="title"]', $form)->item(0)->getAttribute('value'))->toBe($product->name)
            ->and(trim($xpath->query('.//textarea[@name="message"]', $form)->item(0)->textContent))->toBe(route('product', $product->slug))
            ->and($response->getContent())->not->toContain("$(function () { $('#chat_modal').modal('show'); });");
    });
});

describe('sending a question', function () {
    test('starts a conversation with the product\'s seller and mails them, as before', function () {
        $customer = actingAsStoreUser(createStoreUser());
        $product = conversationProduct();
        $url = '/temp/product/'.$product->slug;

        $this->from($url)->post('/temp/conversations', conversationPayload($product))
            ->assertRedirect($url)
            ->assertSessionHasNoErrors();

        expect(Conversation::sole()->only(['sender_id', 'receiver_id', 'title']))->toBe(['sender_id' => $customer->id, 'receiver_id' => $product->user_id, 'title' => 'Question about '.$product->name])
            ->and(Message::sole()->only(['conversation_id', 'user_id', 'message']))->toBe(['conversation_id' => Conversation::sole()->id, 'user_id' => $customer->id, 'message' => 'Is this in stock?'])
            ->and(flashedMessage())->toBe('Message has been sent to seller');
        Mail::assertQueued(ConversationMailManager::class, fn (ConversationMailManager $mail) => $mail->hasTo($product->user->email));
    });

    test('takes a title and a message up to their columns', function () {
        actingAsStoreUser(createStoreUser());
        $product = conversationProduct();

        $this->post('/temp/conversations', conversationPayload($product, ['title' => str_repeat('é', 1000), 'message' => str_repeat('a', 65535)]))
            ->assertSessionHasNoErrors();

        expect(mb_strlen(Conversation::sole()->title))->toBe(1000)
            ->and(strlen(Message::sole()->message))->toBe(65535);
    });

    test('a missing field is refused with its own error and writes nothing', function (string $field) {
        actingAsStoreUser(createStoreUser());
        $product = conversationProduct();
        $payload = conversationPayload($product);
        unset($payload[$field]);

        $this->post('/temp/conversations', $payload)->assertSessionHasErrorsIn('storeConversation', [$field]);

        expectNoConversation();
        Mail::assertNothingQueued();
    })->with([...CONVERSATION_FIELDS, 'product_id']);

    test('hostile input is refused and writes nothing', function (Closure $input, string $field) {
        actingAsStoreUser(createStoreUser());
        $product = conversationProduct();

        $this->post('/temp/conversations', conversationPayload($product, $input($product)))->assertSessionHasErrorsIn('storeConversation', [$field]);

        expectNoConversation();
    })->with([
        'a title longer than conversations.title' => [fn () => ['title' => str_repeat('a', 1001)], 'title'],
        'a multibyte title longer than conversations.title' => [fn () => ['title' => str_repeat('ü', 1001)], 'title'],
        'a message longer than messages.message' => [fn () => ['message' => str_repeat('a', 65536)], 'message'],
        'a title that is an array' => [fn () => ['title' => ['a']], 'title'],
        'a message that is an array' => [fn () => ['message' => ['a']], 'message'],
        'a product id that is an array' => [fn (Product $product) => ['product_id' => [$product->id]], 'product_id'],
        'a product id that is not a number' => [fn () => ['product_id' => 'abc'], 'product_id'],
        'a product that does not exist' => [fn (Product $product) => ['product_id' => $product->id + 1000], 'product_id'],
        'an unpublished product' => [fn () => ['product_id' => conversationProduct(['published' => false])->id], 'product_id'],
        'a product not yet approved' => [fn () => ['product_id' => conversationProduct(['approved' => 0])->id], 'product_id'],
    ]);

    test('keeps markup as text, stored as sent and printed escaped in the conversation', function () {
        $customer = actingAsStoreUser(createStoreUser());
        $product = conversationProduct();

        $this->post('/temp/conversations', conversationPayload($product, ['title' => '<b>Spices</b> & "Co"', 'message' => '<i>Hello</i> & bye']))->assertSessionHasNoErrors();

        expect(Conversation::sole()->title)->toBe('<b>Spices</b> & "Co"')
            ->and(Message::sole()->message)->toBe('<i>Hello</i> & bye');

        $this->get(route('conversations.show', encrypt(Conversation::sole()->id)))->assertOk()
            ->assertSee('&lt;i&gt;Hello&lt;/i&gt; &amp; bye', false)
            ->assertDontSee('<i>Hello</i>', false);
        expect($customer->exists)->toBeTrue();
    });

    test('ignores a sender or receiver it is sent: the sender is the caller, the receiver the product\'s seller', function () {
        $customer = actingAsStoreUser(createStoreUser());
        $product = conversationProduct();
        $other = createStoreUser('customer', 'other@example.com');

        $this->post('/temp/conversations', conversationPayload($product, ['sender_id' => $other->id, 'receiver_id' => $other->id, 'user_id' => $other->id]))
            ->assertSessionHasNoErrors();

        expect(Conversation::sole()->only(['sender_id', 'receiver_id']))->toBe(['sender_id' => $customer->id, 'receiver_id' => $product->user_id])
            ->and(Message::sole()->user_id)->toBe($customer->id);
    });

    test('a guest is sent to sign in and nothing is written', function () {
        $product = conversationProduct();

        $this->post('/temp/conversations', conversationPayload($product))->assertRedirect(route('shop.login'));

        expectNoConversation();
        Mail::assertNothingQueued();
    });
});
