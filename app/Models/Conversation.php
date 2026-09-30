<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    public function messages(){
        return $this->hasMany(Message::class);
    }

    public function sender(){
        return $this->belongsTo(StoreUser::class, 'sender_id');
    }

    public function receiver(){
        return $this->belongsTo(StoreUser::class, 'receiver_id');
    }
}
