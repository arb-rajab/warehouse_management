<?php

namespace App\Services\Store;

use App\Mail\PaymentGatewaySettingsChangedMail;
use App\Models\StoreUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mails every active Super Admin (SuperAdminRecipients) that the payment
 * gateway settings were changed: by whom, when, from which IP, and which
 * gateways and fields, with how each changed (a switch as enabled or
 * disabled). Never a value or a secret (.ai/rules/admin-env-overrides.md,
 * "Security").
 *
 * Sent at once, not queued, through the mailer the shop uses now: a save of
 * this page never changes the mail settings, so these are the settings as
 * they were before the change. Runs after the save has been committed; a mail
 * that cannot be sent is logged by exception class only and never undoes or
 * fails the save.
 */
final class PaymentGatewaySettingsChangeNotifier
{
    /** @param  array<string, string>  $changes  `<gateway>.<field>` => set|changed|removed|enabled|disabled */
    public function notify(StoreUser $actor, array $changes, ?string $ip, CarbonInterface $at): void
    {
        try {
            $recipients = app(SuperAdminRecipients::class)->all();
        } catch (Throwable $exception) {
            Log::warning('The payment gateway change notification could not be prepared.', ['exception' => $exception::class]);

            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient['email'])->send(new PaymentGatewaySettingsChangedMail([
                    'changes' => $changes,
                    'actor_name' => (string) $actor->name,
                    'actor_email' => (string) $actor->email,
                    'changed_at' => $at->copy()->utc()->format('Y-m-d H:i').' UTC',
                    'ip' => $ip,
                    'link' => route('payment_method.index'),
                ], $recipient['user']));
            } catch (Throwable $exception) {
                Log::warning('The payment gateway change notification could not be sent.', ['exception' => $exception::class]);
            }
        }
    }
}
