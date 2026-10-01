<?php

namespace App\Services\Revoke;

use Illuminate\Support\Facades\Http;

class AppleRevoke implements ProviderRevoke
{
    public function apply()
    {
        $grant_type = "refresh_token";
        $refresh_token = auth()->user()->refresh_token;
        $client_id = config('services.apple.client_id');
        $client_secret = config('services.apple.client_secret');
        $redirect_uri = config('services.apple.redirect');

        $server_output = Http::asForm()->post('https://appleid.apple.com/auth/token', [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => $grant_type,
            'refresh_token' => $refresh_token,
            'redirect_uri' => $redirect_uri,
        ]);

        $access_token = $server_output->object()->access_token;

        $revoke_output = Http::asForm()->post('https://appleid.apple.com/auth/revoke', [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'token_type_hint' => $grant_type,
            'token' => $access_token,
        ]);

        return $revoke_output->ok();
    }
}
