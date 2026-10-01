<?php

namespace App\Http\Controllers;

use App\Mail\EmailManager;
use App\Models\StoreUser;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Mail;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NewsletterController extends Controller implements HasMiddleware
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
            new Middleware('permission:send_newsletter', only: ['index', 'send', 'testEmail']),
        ];
    }
    public function index(Request $request)
    {
        $users = StoreUser::all();
        $subscribers = Subscriber::all();
        return view('shop.backend.marketing.newsletters.index', compact('users', 'subscribers'));
    }

    public function send(Request $request)
    {
        if (env('MAIL_USERNAME') != null) {
            //sends newsletter to selected users
        	if ($request->has('user_emails')) {
                foreach ($request->user_emails as $key => $email) {
                    $array['view'] = 'shop.emails.newsletter';
                    $array['subject'] = $request->subject;
                    $array['from'] = config('mail.from.address');
                    $array['content'] = $request->content;

                    try {
                        Mail::to($email)->queue(new EmailManager($array));
                    } catch (\Exception $e) {
                        //dd($e);
                    }
            	}
            }

            //sends newsletter to subscribers
            if ($request->has('subscriber_emails')) {
                foreach ($request->subscriber_emails as $key => $email) {
                    $array['view'] = 'shop.emails.newsletter';
                    $array['subject'] = $request->subject;
                    $array['from'] = config('mail.from.address');
                    $array['content'] = $request->content;

                    try {
                        Mail::to($email)->queue(new EmailManager($array));
                    } catch (\Exception $e) {
                        //dd($e);
                    }
            	}
            }
        }
        else {
            flash(translate('Please configure SMTP first'))->error();
            return back();
        }

    	flash(translate('Newsletter has been send'))->success();
    	return redirect()->route('shop.admin.dashboard');
    }

    public function testEmail(Request $request){
        $array['view'] = 'shop.emails.newsletter';
        $array['subject'] = "SMTP Test";
        $array['from'] = config('mail.from.address');
        $array['content'] = "This is a test email.";

        try {
            Mail::to($request->email)->queue(new EmailManager($array));
        } catch (\Exception $e) {
            dd($e);
        }

        flash(translate('An email has been sent.'))->success();
        return back();
    }
}
