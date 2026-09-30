<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\StoreUser;

/**
 * Who may read and write a store conversation: the customer who started it
 * and the seller it was started with. The app API's message routes took any
 * conversation by id.
 */
class ConversationPolicy
{
    public function participate(StoreUser $user, Conversation $conversation): bool
    {
        return $conversation->sender_id === $user->id
            || $conversation->receiver_id === $user->id;
    }
}
