<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one push notification through Firebase Cloud Messaging's HTTP v1 API,
 * the only one Google still serves: the legacy `fcm/send` endpoint with a server
 * key was shut down, so every push sent that way was lost.
 *
 * The service-account file named by FIREBASE_CREDENTIALS (relative to storage/)
 * signs in, and FIREBASE_PROJECT names the Firebase project (FCM_PROJECT_ID is
 * the fallback). A token belongs to that project: the app that registered it
 * has to be added to the same project in the Firebase console.
 */
class FcmPushService
{
    public const string SENT = 'sent';

    /** The token is not registered with the project (app uninstalled, token expired or from another project). */
    public const string INVALID_TOKEN = 'invalid_token';

    public const string NOT_CONFIGURED = 'not_configured';

    public const string FAILED = 'failed';

    private const string ACCESS_TOKEN_CACHE_KEY = 'fcm-v1-access-token';

    public function project(): ?string
    {
        return config('services.fcm.firebase_project') ?: config('services.fcm.project_id') ?: null;
    }

    public function credentialsPath(): ?string
    {
        $file = config('services.fcm.firebase_credentials');

        return blank($file) ? null : storage_path($file);
    }

    public function isConfigured(): bool
    {
        $path = $this->credentialsPath();

        return $this->project() !== null && $path !== null && is_file($path);
    }

    /**
     * @param  array<string, scalar|null>  $data  delivered to the app beside the notification; values are sent as strings
     * @return self::SENT|self::INVALID_TOKEN|self::NOT_CONFIGURED|self::FAILED
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): string
    {
        if (! $this->isConfigured()) {
            Log::warning('Push not sent: FIREBASE_CREDENTIALS or FIREBASE_PROJECT is not set up.');

            return self::NOT_CONFIGURED;
        }

        try {
            $response = Http::withToken($this->accessToken())->timeout(10)->acceptJson()
                ->post('https://fcm.googleapis.com/v1/projects/'.$this->project().'/messages:send', [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => array_map(fn ($value) => (string) $value, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK', ...$data]),
                        'android' => ['priority' => 'high', 'notification' => ['sound' => 'default']],
                    ],
                ]);
        } catch (Throwable $exception) {
            Log::warning('Push not sent: '.$exception->getMessage());

            return self::FAILED;
        }

        if ($response->successful()) {
            return self::SENT;
        }

        $status = $response->json('error.status');
        Log::warning('Push refused by FCM: '.$response->status().' '.$response->body());

        return in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true) ? self::INVALID_TOKEN : self::FAILED;
    }

    /** An OAuth token lasts an hour; one is reused for 50 minutes. */
    private function accessToken(): string
    {
        return Cache::remember(self::ACCESS_TOKEN_CACHE_KEY, now()->addMinutes(50), fn () => $this->fetchAccessToken());
    }

    protected function fetchAccessToken(): string
    {
        $client = new GoogleClient;
        $client->setAuthConfig((string) $this->credentialsPath());
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $token = $client->fetchAccessTokenWithAssertion();

        return (string) ($token['access_token'] ?? throw new \RuntimeException('Google gave no access token: '.json_encode($token['error'] ?? 'unknown error')));
    }
}
