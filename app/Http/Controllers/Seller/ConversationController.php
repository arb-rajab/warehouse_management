<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Seller\StoreMessageRequest;
use App\Models\BusinessSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProductQuery;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (BusinessSetting::where('type', 'conversation_system')->first()?->value == 1) {
            $conversations = Conversation::where('sender_id', Auth::user()->id)->orWhere('receiver_id', Auth::user()->id)->orderBy('created_at', 'desc')->paginate(5);
            return view('shop.seller.conversations.index', compact('conversations'));
        } else {
            flash(__('storefront.support.conversation_is_disabled_at_this_moment'))->warning();
            return back();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $conversation = Conversation::findOrFail(decrypt($id));
        Gate::authorize('participate', $conversation);
        if ($conversation->sender_id == Auth::user()->id) {
            $conversation->sender_viewed = 1;
        } elseif ($conversation->receiver_id == Auth::user()->id) {
            $conversation->receiver_viewed = 1;
        }
        $conversation->save();
        return view('shop.seller.conversations.show', compact('conversation'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function refresh(Request $request)
    {
        $conversation = Conversation::findOrFail(decrypt($request->id));
        Gate::authorize('participate', $conversation);
        if ($conversation->sender_id == Auth::user()->id) {
            $conversation->sender_viewed = 1;
            $conversation->save();
        } else {
            $conversation->receiver_viewed = 1;
            $conversation->save();
        }
        return view('shop.frontend.partials.messages', compact('conversation'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function message_store(StoreMessageRequest $request)
    {
        $conversation = Conversation::findOrFail($request->conversation_id);
        Gate::authorize('participate', $conversation);

        $message = new Message;
        $message->conversation_id = $conversation->id;
        $message->user_id = Auth::user()->id;
        $message->message = $request->message;
        $message->save();
        if ($conversation->sender_id == Auth::user()->id) {
            $conversation->receiver_viewed = "1";
        } elseif ($conversation->receiver_id == Auth::user()->id) {
            $conversation->sender_viewed = "1";
        }
        $conversation->save();

        return back();
    }

}
