<?php

namespace App\Console\Commands;

use App\Services\FcmPushService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('store:send-test-push {token : The device token the app registered} {--title=Test push : The notification title} {--body=If you can read this, push works. : The notification body}')]
#[Description('Send one test push notification to a device token, through the same service the order pushes use')]
class SendTestPushCommand extends Command
{
    public function handle(FcmPushService $push): int
    {
        if (! $push->isConfigured()) {
            $this->components->error('Push is not set up: FIREBASE_CREDENTIALS must name a service-account file under storage/ that exists, and FIREBASE_PROJECT (or FCM_PROJECT_ID) the Firebase project.');

            return self::FAILURE;
        }

        $this->components->info('Sending with Firebase project "'.$push->project().'".');

        $result = $push->send((string) $this->argument('token'), (string) $this->option('title'), (string) $this->option('body'), ['item_type' => 'test', 'item_type_id' => 0]);

        return match ($result) {
            FcmPushService::SENT => tap(self::SUCCESS, fn () => $this->components->info('Sent. The phone should show it.')),
            FcmPushService::INVALID_TOKEN => tap(self::FAILURE, fn () => $this->components->error('Firebase does not know this token for the project "'.$push->project().'": it is from another project, expired, or the app was uninstalled.')),
            default => tap(self::FAILURE, fn () => $this->components->error('The push was not sent; the reason is in storage/logs/laravel.log.')),
        };
    }
}
