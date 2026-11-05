<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\SearchNewsletterRecipientsRequest;
use App\Http\Requests\Store\Admin\SendNewsletterRequest;
use App\Mail\EmailManager;
use App\Models\StoreUser;
use App\Models\Subscriber;
use App\Support\SearchOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller implements HasMiddleware
{
    use SearchesOptions;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:send_newsletter')];
    }

    /**
     * Recipients are not listed here: the form searches them through the
     * bounded recipients() endpoint, where the Blade screen loaded every
     * user and subscriber email into the page.
     */
    public function index(): Response
    {
        return Inertia::render('Store/Admin/Marketing/Newsletters/Index', [
            'smtpConfigured' => filled(config('mail.mailers.smtp.username')),
        ]);
    }

    /** The typeahead behind the recipient lists (StoreSearchSelect): the email is the id. */
    public function recipients(SearchNewsletterRecipientsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);

        if ($request->validated('kind') === 'users') {
            $users = StoreUser::query()->select(['id', 'name', 'email'])->whereNotNull('email')->where('email', '!=', '');
            if ($term !== '') {
                $this->whereWordsMatch($users, ['email', 'name'], $term);
            }
            $this->orderByRelevance($users, 'email', $term);

            return $this->optionsPage($users, fn (StoreUser $user): array => SearchOption::recipient((string) $user->email, $user->name));
        }

        $subscribers = Subscriber::query()->select(['id', 'email'])->whereNotNull('email')->where('email', '!=', '');
        if ($term !== '') {
            $this->whereWordsMatch($subscribers, ['email'], $term);
        }
        $this->orderByRelevance($subscribers, 'email', $term);

        return $this->optionsPage($subscribers, fn (Subscriber $subscriber): array => SearchOption::recipient((string) $subscriber->email));
    }

    public function send(SendNewsletterRequest $request): RedirectResponse
    {
        if (blank(config('mail.mailers.smtp.username'))) {
            throw ValidationException::withMessages(['smtp' => __('store_admin.please_configure_smtp_first')]);
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
