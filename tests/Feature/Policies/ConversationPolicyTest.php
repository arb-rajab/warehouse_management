<?php

use App\Models\Conversation;
use App\Policies\ConversationPolicy;

test('both sides of a conversation may take part in it, and nobody else', function () {
    $customer = createStoreUser('customer');
    $seller = createStoreUser('seller');
    $otherCustomer = createStoreUser('customer');
    $conversation = Conversation::forceCreate(['sender_id' => $customer->id, 'receiver_id' => $seller->id, 'title' => 'Question'])->fresh();

    $policy = new ConversationPolicy;

    expect($policy->participate($customer, $conversation))->toBeTrue()
        ->and($policy->participate($seller, $conversation))->toBeTrue()
        ->and($policy->participate($otherCustomer, $conversation))->toBeFalse();
});
