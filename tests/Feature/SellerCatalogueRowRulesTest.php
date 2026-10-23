<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

test('seller bulk row limits refuse overflow and roll back all rows at the real endpoint', function (array $changes) {
    actingAsStoreUser(createStoreUser('seller'));
    $row = catalogueImportRow(['thumbnail_img' => '', 'photos' => '']);
    $before = [Product::count(), DB::table('product_stocks')->count(), DB::table('uploads')->count()];
    $this->post(route('seller.bulk_product_upload'), ['bulk_file' => catalogueImportFile([$row, [...$row, ...$changes]])])->assertSessionHasErrors('bulk_file');
    expect([Product::count(), DB::table('product_stocks')->count(), DB::table('uploads')->count()])->toBe($before);
})->with([
    'unit width' => [['unit' => str_repeat('é', 21)]],
    'price ceiling' => [['unit_price' => '1000000000000000000']],
    'price precision' => [['unit_price' => '999999999999999999.999']],
    'stock ceiling' => [['current_stock' => '2147483648']],
    'tags width' => [['tags' => str_repeat('é', 501)]],
    'video width' => [['video_link' => 'https://example.com/'.str_repeat('a', 82)]],
]);

test('seller bulk accepts exact storage boundaries and preserves another sellers rows', function () {
    $seller = actingAsStoreUser(createStoreUser('seller'));
    $noise = createStoreProduct(['user_id' => createStoreUser('seller')->id]);
    $before = $noise->fresh()->getAttributes();
    $row = catalogueImportRow(['unit' => str_repeat('é', 20), 'unit_price' => '999999999999999999.99', 'current_stock' => '2147483647', 'tags' => str_repeat('é', 500), 'video_link' => 'https://example.com/'.str_repeat('a', 100 - strlen('https://example.com/')), 'thumbnail_img' => '', 'photos' => '']);
    $this->post(route('seller.bulk_product_upload'), ['bulk_file' => catalogueImportFile([$row])])->assertSessionHasNoErrors();
    $product = Product::query()->where('user_id', $seller->id)->sole();
    expect($product->unit)->toBe($row['unit'])->and($product->tags)->toBe($row['tags'])
        ->and((int) $product->stocks()->sole()->qty)->toBe(2147483647)
        ->and($noise->fresh()->getAttributes())->toBe($before);
    if (DB::getDriverName() === 'mysql') {
        expect($product->unit_price)->toBe('999999999999999999.99');
    }
});

test('seller conversation already enforces the exact MySQL byte boundary and membership', function () {
    $seller = actingAsStoreUser(createStoreUser('seller'));
    $customer = createStoreUser();
    $conversation = Conversation::forceCreate(['sender_id' => $customer->id, 'receiver_id' => $seller->id, 'title' => 'Question']);
    $foreign = Conversation::forceCreate(['sender_id' => $customer->id, 'receiver_id' => createStoreUser('seller')->id, 'title' => 'Other question']);
    $input = ['conversation_id' => $conversation->id, 'message' => str_repeat('é', 32767).'a'];
    $this->post(route('seller.conversations.message_store'), $input)->assertSessionHasNoErrors();
    expect(Message::query()->sole()->message)->toBe($input['message'])->and(Message::query()->sole()->user_id)->toBe($seller->id);
    $before = [DB::table('messages')->get()->toJson(), DB::table('conversations')->get()->toJson()];
    $this->post(route('seller.conversations.message_store'), [...$input, 'message' => str_repeat('é', 32768)])->assertSessionHasErrors('message');
    $this->post(route('seller.conversations.message_store'), [...$input, 'conversation_id' => $foreign->id, 'message' => []])->assertForbidden();
    expect([DB::table('messages')->get()->toJson(), DB::table('conversations')->get()->toJson()])->toBe($before);
});
