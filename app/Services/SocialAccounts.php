<?php

namespace App\Services;

use App\Models\StoreUser;
use Laravel\Socialite\AbstractUser;

/**
 * Finds or creates the store account a social login (Google, Facebook,
 * Twitter, Apple) belongs to, for the web callbacks and the mobile API alike.
 *
 * An account is identified by the id the provider itself returned, never by a
 * value the client sent. An existing account is linked by email only when the
 * provider says it has verified that email, because otherwise anyone could
 * open a provider account claiming a victim's address and be signed in as the
 * victim. Linking and signing in never touch `user_type`.
 */
class SocialAccounts
{
    /**
     * @param  string  $provider  The provider's name, as stored in `users.provider`.
     * @param  string|null  $name  Used for a new account when the provider returns no name (Apple's token carries none).
     * @return StoreUser|null Null when the email belongs to an existing account that this login cannot be linked to.
     */
    public function resolve(string $provider, AbstractUser $social, ?string $name = null): ?StoreUser
    {
        $providerId = (string) $social->getId();

        // Rows linked before `provider` was stored have none; the id alone
        // identifies those, as it always did.
        $linked = StoreUser::where('provider_id', $providerId)
            ->where(fn ($query) => $query->whereNull('provider')->orWhere('provider', $provider))
            ->first();

        if ($linked !== null) {
            $this->refreshTokens($linked, $social);

            if ($provider === 'apple' && $this->sharesEmail($social) && ! $this->emailBelongsToAnother($social, $linked)) {
                $linked->email = $social->getEmail();
            }

            $linked->provider = $provider;
            $linked->save();

            return $linked;
        }

        $email = $social->getEmail();
        $existing = $email !== null && $email !== ''
            ? StoreUser::where('email', $email)->first()
            : null;

        if ($existing !== null) {
            // A provider id already on the account is another identity's: the
            // lookup above would have found this one.
            if ($existing->provider_id !== null || ! $this->providerVerifiedEmail($social)) {
                return null;
            }

            $user = $existing;
        } else {
            $user = new StoreUser;
            $user->user_type = 'customer';
            $user->name = $social->getName() ?: $name ?: ucfirst($provider).' User';
            $user->email = $email !== '' ? $email : null;
            $user->email_verified_at = $email !== null && $this->providerVerifiedEmail($social) ? now() : null;
        }

        $user->provider_id = $providerId;
        $user->provider = $provider;
        $this->refreshTokens($user, $social);
        $user->save();

        return $user;
    }

    /**
     * Stores the provider's tokens on the account. The token properties live on
     * the Two\User and One\User subclasses rather than AbstractUser, so they
     * are read from the object's public properties.
     */
    private function refreshTokens(StoreUser $user, AbstractUser $social): void
    {
        $properties = get_object_vars($social);

        $user->access_token = $properties['token'] ?? null;

        if (($properties['refreshToken'] ?? null) !== null) {
            $user->refresh_token = $properties['refreshToken'];
        }
    }

    /**
     * Whether the provider vouches for the email it returned. Google and
     * Apple say so in the profile they return; Facebook and Twitter do not, so
     * they never link to an existing account.
     */
    private function providerVerifiedEmail(AbstractUser $social): bool
    {
        $profile = is_array($social->user) ? $social->user : [];

        return filter_var($profile['email_verified'] ?? $profile['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Apple's "hide my email" relay addresses are flagged private and are not
     * written over the account's own.
     */
    private function sharesEmail(AbstractUser $social): bool
    {
        $profile = is_array($social->user) ? $social->user : [];

        return ! isset($profile['is_private_email']) && $social->getEmail() !== null;
    }

    private function emailBelongsToAnother(AbstractUser $social, StoreUser $user): bool
    {
        return StoreUser::where('email', $social->getEmail())->whereKeyNot($user->getKey())->exists();
    }
}
