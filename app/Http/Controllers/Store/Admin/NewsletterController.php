<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\SearchNewsletterRecipientsRequest;
use App\Http\Requests\Store\Admin\SendNewsletterRequest;
use App\Mail\EmailManager;
use App\Models\StoreUser;
use App\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class NewsletterController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:send_newsletter')];
    }

    public function index(): View
    {
        return view('shop.backend.marketing.newsletters.index', [
            'users' => StoreUser::query()->select('email')->get(),
            'subscribers' => Subscriber::query()->select('email')->get(),
        ]);
    }

    public function recipients(SearchNewsletterRecipientsRequest $request): JsonResponse
    {
        $query = $request->validated('kind') === 'users' ? StoreUser::query() : Subscriber::query();
        $search = $request->validated('q');

        return response()->json($query->select(['email'])->whereNotNull('email')->where('email', '!=', '')
            ->when($search, fn (Builder $query) => $query->where('email', 'like', '%'.$search.'%'))
            ->distinct()->orderBy('email')->limit(30)->pluck('email')->all());
    }

    public function send(SendNewsletterRequest $request): RedirectResponse
    {
        if (blank(config('mail.mailers.smtp.username'))) {
            throw ValidationException::withMessages(['smtp' => translate('Please configure SMTP first')]);
        }

        $recipients = array_unique([
            ...$request->validated('user_emails', []),
            ...$request->validated('subscriber_emails', []),
        ]);
        $message = [
            'view' => 'shop.emails.newsletter',
            'subject' => $request->validated('subject'),
            'from' => config('mail.from.address'),
            'content' => $request->validated('content'),
        ];
        foreach ($recipients as $email) {
            Mail::to($email)->queue(new EmailManager($message));
        }

        return redirect()->route('shop.admin.dashboard');
    }
}
