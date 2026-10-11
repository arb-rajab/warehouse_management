<?php

use App\Models\Conversation;
use App\Models\Message;

test('a store message resolves only its conversation and author', function () {
    $author = createStoreUser();
    $noiseUser = createStoreUser();
    $conversation = Conversation::forceCreate(['sender_id' => $author->id, 'receiver_id' => $noiseUser->id]);
    $noise = Conversation::forceCreate(['sender_id' => $noiseUser->id, 'receiver_id' => $author->id]);
    $message = Message::forceCreate(['conversation_id' => $conversation->id, 'user_id' => $author->id, 'message' => 'mine']);
    Message::forceCreate(['conversation_id' => $noise->id, 'user_id' => $noiseUser->id, 'message' => 'noise']);

    expect($message->conversation->id)->toBe($conversation->id)
        ->and($message->user->id)->toBe($author->id);
});
