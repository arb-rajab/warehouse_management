<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationRequest;
use App\Mail\ConversationMailManager;
use App\Models\BusinessSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mail;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ConversationController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See Auth\LoginController::middleware()'s docblock for why this
     * replaces the constructor-based $this->middleware() call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:delete_product_conversations', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (BusinessSetting::where('type', 'conversation_system')->first()?->value == 1) {
            $conversations = Conversation::with(['sender', 'receiver.shop', 'messages'])->where('sender_id', Auth::user()->id)->orWhere('receiver_id', Auth::user()->id)->orderBy('created_at', 'desc')->paginate(8);
            return view('shop.frontend.user.conversations.index', compact('conversations'));
        } else {
            flash(__('storefront.support.conversation_is_disabled_at_this_moment'))->warning();
            return back();
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(StoreConversationRequest $request)
    {
        $user_type = Product::findOrFail($request->validated('product_id'))->user->user_type;

        $conversation = new Conversation;
        $conversation->sender_id = Auth::user()->id;
        $conversation->receiver_id = Product::findOrFail($request->validated('product_id'))->user->id;
        $conversation->title = $request->validated('title');

        if ($conversation->save()) {
            $message = new Message;
            $message->conversation_id = $conversation->id;
            $message->user_id = Auth::user()->id;
            $message->message = $request->validated('message');

            if ($message->save()) {
                $this->send_message_to_seller($conversation, $message, $user_type);
            }
        }

        flash(__('storefront.product.message_has_been_sent_to_seller'))->success();
        return back();
    }

    public function send_message_to_seller($conversation, $message, $user_type)
    {
        $array['view'] = 'shop.emails.conversation';
        $array['subject_key'] = 'storefront.support.sender_subject';
        $array['subject_parameters'] = ['name' => Auth::user()->name];
        $array['from'] = config('mail.from.address');
        $array['content_key'] = 'storefront.support.received_message';
        $array['content_parameters'] = ['name' => Auth::user()->name];
        $array['sender'] = Auth::user()->name;

        if ($user_type == 'admin') {
            $array['link'] = route('conversations.admin_show', $conversation->id);
        } else {
            $array['link'] = route('conversations.show', encrypt($conversation->id));
        }

        $array['details'] = $message->message;

        try {
            Mail::to($conversation->receiver->email)->queue(new ConversationMailManager($array, $conversation->receiver));
        } catch (\Exception $e) {
            //dd($e->getMessage());
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
        $conversation = Conversation::with('messages.user.shop')->findOrFail(decrypt($id));
        if ($conversation->sender_id == Auth::user()->id) {
            $conversation->sender_viewed = 1;
        } elseif ($conversation->receiver_id == Auth::user()->id) {
            $conversation->receiver_viewed = 1;
        }
        $conversation->save();
        return view('shop.frontend.user.conversations.show', compact('conversation'));
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function refresh(Request $request)
    {
        $conversation = Conversation::with('messages.user.shop')->findOrFail(decrypt($request->id));
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
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $conversation = Conversation::findOrFail(decrypt($id));
        $conversation->messages()->delete();

        if (Conversation::destroy(decrypt($id))) {
            flash(__('storefront.support.conversation_has_been_deleted_successfully'))->success();
            return back();
        }
    }
}
