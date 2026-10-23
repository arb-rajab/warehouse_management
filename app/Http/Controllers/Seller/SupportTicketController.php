<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Seller\StoreSupportTicketRequest;
use App\Http\Requests\Seller\StoreTicketReplyRequest;
use App\Mail\SupportMailManager;
use App\Models\StoreUser;
use App\Models\Ticket;
use App\Models\TicketReply;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mail;

class SupportTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $tickets = Ticket::where('user_id', Auth::user()->id)->orderBy('created_at', 'desc')->paginate(9);
        return view('shop.seller.support_ticket.index', compact('tickets'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(StoreSupportTicketRequest $request)
    {
        $ticket = new Ticket;
        $ticket->code = max(100000, (Ticket::latest()->first() != null ? Ticket::latest()->first()->code + 1 : 0)).date('s');
        $ticket->user_id = Auth::user()->id;
        $ticket->subject = $request->subject;
        $ticket->details = $request->details;
        $ticket->files = $request->attachments;

        if($ticket->save()){
            $this->send_support_mail_to_admin($ticket);
            flash(translate('Ticket has been sent successfully'))->success();
            return redirect()->route('seller.support_ticket.index');
        }
        else{
            flash(translate('Something went wrong'))->error();
        }
    }

    public function send_support_mail_to_admin($ticket){
        $array['view'] = 'shop.emails.support';
        $array['subject'] = translate('Support ticket Code is').':- '.$ticket->code;
        $array['from'] = config('mail.from.address');
        $array['content'] = translate('Hi. A ticket has been created. Please check the ticket.');
        $array['link'] = route('support_ticket.admin_show', encrypt($ticket->id));
        $array['sender'] = $ticket->user->name;
        $array['details'] = $ticket->details;

        try {
            Mail::to(StoreUser::where('user_type', 'admin')->first()->email)->queue(new SupportMailManager($array));
        } catch (\Exception $e) {
            // dd($e->getMessage());
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
        $ticket = Ticket::where('user_id', Auth::user()->id)->findOrFail(decrypt($id));
        $ticket->client_viewed = 1;
        $ticket->save();
        $ticket_replies = $ticket->ticketreplies;
        return view('shop.seller.support_ticket.show', compact('ticket','ticket_replies'));
    }

    public function ticket_reply_store(StoreTicketReplyRequest $request)
    {
        // The caller's own ticket, replied to as the caller: both used to come from the request.
        $ticket = Ticket::where('user_id', Auth::user()->id)->findOrFail($request->ticket_id);
        $ticket_reply = new TicketReply;
        $ticket_reply->ticket_id = $ticket->id;
        $ticket_reply->user_id = Auth::user()->id;
        $ticket_reply->reply = $request->reply;
        $ticket_reply->files = $request->attachments;
        $ticket_reply->ticket->viewed = 0;
        $ticket_reply->ticket->status = 'pending';
        $ticket_reply->ticket->save();
        if($ticket_reply->save()){

            flash(translate('Reply has been sent successfully'))->success();
            return back();
        }
        else{
            flash(translate('Something went wrong'))->error();
        }
    }

}
