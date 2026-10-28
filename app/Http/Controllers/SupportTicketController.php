<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportTicketRequest;
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
        $tickets = Ticket::where('user_id', Auth::user()->id)->orderBy('created_at', 'desc')->paginate(10);
        return view('shop.frontend.user.support_ticket.index', compact('tickets'));
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
    public function store(StoreSupportTicketRequest $request)
    {
        $ticket = new Ticket;
        $ticket->code = max(100000, (Ticket::latest()->first() != null ? Ticket::latest()->first()->code + 1 : 0)).date('s');
        $ticket->user_id = Auth::user()->id;
        $ticket->subject = $request->validated('subject');
        $ticket->details = $request->validated('details');
        $ticket->files = $request->validated('attachments');

        if($ticket->save()){
            $this->send_support_mail_to_admin($ticket);
            flash(__('storefront.support.ticket_has_been_sent_successfully'))->success();
            return redirect()->route('support_ticket.index');
        }
        else{
            flash(__('storefront.common.something_went_wrong'))->error();
        }


    }

    public function send_support_mail_to_admin($ticket){
        $array['view'] = 'shop.emails.support';
        $array['subject'] = __('storefront.support.ticket_subject', ['code' => $ticket->code]);
        $array['from'] = config('mail.from.address');
        $array['content'] = __('storefront.support.ticket_created');
        $array['link'] = route('support_ticket.admin_show', encrypt($ticket->id));
        $array['sender'] = $ticket->user->name;
        $array['details'] = $ticket->details;

        // dd($array);
        // dd(StoreUser::where('user_type', 'admin')->first()->email);
        try {
            Mail::to(StoreUser::where('user_type', 'admin')->first()->email)->queue(new SupportMailManager($array));
        } catch (\Exception $e) {
            // dd($e->getMessage());
        }
    }

    public function send_support_reply_email_to_user($ticket, $tkt_reply){
        $array['view'] = 'shop.emails.support';
        $array['subject'] = __('storefront.support.ticket_subject', ['code' => $ticket->code]);
        $array['from'] = config('mail.from.address');
        $array['content'] = __('storefront.support.ticket_created');
        $array['link'] = route('support_ticket.show', encrypt($ticket->id));
        $array['sender'] = $tkt_reply->user->name;
        $array['details'] = $tkt_reply->reply;

        try {
            Mail::to($ticket->user->email)->queue(new SupportMailManager($array));
        } catch (\Exception $e) {
            //dd($e->getMessage());
        }
    }


    public function seller_store(Request $request)
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

            flash(__('storefront.support.reply_has_been_sent_successfully'))->success();
            return back();
        }
        else{
            flash(__('storefront.common.something_went_wrong'))->error();
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
        return view('shop.frontend.user.support_ticket.show', compact('ticket','ticket_replies'));
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
        //
    }
}
