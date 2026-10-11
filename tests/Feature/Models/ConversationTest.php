<?php

use App\Models\Conversation;
use App\Models\Message;

test('store conversation relations resolve only their own participants and messages', function () {
    $sender = createStoreUser();
    $receiver = createStoreUser('admin');
    $noiseUser = createStoreUser();
    $conversation = Conversation::forceCreate(['sender_id' => $sender->id, 'receiver_id' => $receiver->id]);
    $noise = Conversation::forceCreate(['sender_id' => $noiseUser->id, 'receiver_id' => $receiver->id]);
    $message = Message::forceCreate(['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'message' => 'mine']);
    Message::forceCreate(['conversation_id' => $noise->id, 'user_id' => $noiseUser->id, 'message' => 'noise']);

    expect($conversation->sender->id)->toBe($sender->id)
        ->and($conversation->receiver->id)->toBe($receiver->id)
        ->and($conversation->messages->pluck('id')->all())->toBe([$message->id]);
});
