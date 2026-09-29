<?php

namespace App\Http\Controllers;

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
            new Middleware('permission:view_all_product_conversations', only: ['admin_index']),
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
            $conversations = Conversation::where('sender_id', Auth::user()->id)->orWhere('receiver_id', Auth::user()->id)->orderBy('created_at', 'desc')->paginate(8);
            return view('shop.frontend.user.conversations.index', compact('conversations'));
        } else {
            flash(translate('Conversation is disabled at this moment'))->warning();
            return back();
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function admin_index()
    {
        if (BusinessSetting::where('type', 'conversation_system')->first()?->value == 1) {
            $conversations = Conversation::orderBy('created_at', 'desc')->get();
            return view('shop.backend.support.conversations.index', compact('conversations'));
        } else {
            flash(translate('Conversation is disabled at this moment'))->warning();
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
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $user_type = Product::findOrFail($request->product_id)->user->user_type;

        $conversation = new Conversation;
        $conversation->sender_id = Auth::user()->id;
        $conversation->receiver_id = Product::findOrFail($request->product_id)->user->id;
        $conversation->title = $request->title;

        if ($conversation->save()) {
            $message = new Message;
            $message->conversation_id = $conversation->id;
            $message->user_id = Auth::user()->id;
            $message->message = $request->message;

            if ($message->save()) {
                $this->send_message_to_seller($conversation, $message, $user_type);
            }
        }

        flash(translate('Message has been sent to seller'))->success();
        return back();
    }

    public function send_message_to_seller($conversation, $message, $user_type)
    {
        $array['view'] = 'shop.emails.conversation';
        $array['subject'] = translate('Sender').':- '. Auth::user()->name;
        $array['from'] = env('MAIL_FROM_ADDRESS');
        $array['content'] = translate('Hi! You recieved a message from ') . Auth::user()->name . '.';
        $array['sender'] = Auth::user()->name;

        if ($user_type == 'admin') {
            $array['link'] = route('conversations.admin_show', encrypt($conversation->id));
        } else {
            $array['link'] = route('conversations.show', encrypt($conversation->id));
        }

        $array['details'] = $message->message;

        try {
            Mail::to($conversation->receiver->email)->queue(new ConversationMailManager($array));
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
        $conversation = Conversation::findOrFail(decrypt($id));
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
        $conversation = Conversation::findOrFail(decrypt($request->id));
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
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function admin_show($id)
    {
        $conversation = Conversation::findOrFail(decrypt($id));
        if ($conversation->sender_id == Auth::user()->id) {
            $conversation->sender_viewed = 1;
        } elseif ($conversation->receiver_id == Auth::user()->id) {
            $conversation->receiver_viewed = 1;
        }
        $conversation->save();
        return view('shop.backend.support.conversations.show', compact('conversation'));
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
            flash(translate('Conversation has been deleted successfully'))->success();
            return back();
        }
    }
}
