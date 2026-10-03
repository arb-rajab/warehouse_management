<?php

namespace App\Services;

use App\Models\StoreUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The store's emailed and texted six-digit codes: password reset and account
 * verification.
 *
 * A code is stored as a keyed hash, stops working after LIFETIME_MINUTES and
 * after MAX_ATTEMPTS wrong tries, works once, and cannot be re-issued sooner
 * than RESEND_COOLDOWN_SECONDS after the last one. Six digits are 900,000
 * guesses, so these limits, together with the `store-auth` throttle, are what
 * make them safe to accept from a guest.
 */
class StoreVerificationCodes
{
    public const LIFETIME_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    private const TABLE = 'wms_store_verification_codes';

    /**
     * Issue the user a new code, replacing any earlier one, and return it in
     * clear text for the caller to send. This is the only time it exists.
     */
    public function issue(StoreUser $user): string
    {
        $code = (string) random_int(100000, 999999);

        DB::table(self::TABLE)->updateOrInsert(
            ['user_id' => $user->getKey()],
            [
                'code_hash' => $this->hash($user, $code),
                'attempts' => 0,
                'issued_at' => now(),
                'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
            ],
        );

        return $code;
    }

    /**
     * Issue a code, or null when the user's last one is younger than the
     * resend cooldown, in which case that code stays the valid one.
     */
    public function issueUnlessCoolingDown(StoreUser $user): ?string
    {
        $issuedAt = DB::table(self::TABLE)->where('user_id', $user->getKey())->value('issued_at');

        if ($issuedAt !== null && Carbon::parse($issuedAt)->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isFuture()) {
            return null;
        }

        return $this->issue($user);
    }

    /**
     * Whether `$code` is the user's live code; if so it is used up.
     *
     * Every call that finds a live code counts as an attempt, claimed with one
     * conditional UPDATE so simultaneous guesses cannot all slip under the cap,
     * and a code that has used its attempts is dead even for the right guess.
     */
    public function consume(StoreUser $user, string $code): bool
    {
        $row = DB::table(self::TABLE)->where('user_id', $user->getKey())->first();

        if ($row === null) {
            return false;
        }

        if (Carbon::parse($row->expires_at)->isPast()) {
            $this->forget($user);

            return false;
        }

        $claimed = DB::table(self::TABLE)
            ->where('user_id', $user->getKey())
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->increment('attempts');

        if ($claimed === 0) {
            $this->forget($user);

            return false;
        }

        if (! hash_equals($row->code_hash, $this->hash($user, trim($code)))) {
            return false;
        }

        // Deleting the row we read is the single-use guarantee: a second
        // request carrying the same right code finds nothing to delete.
        return DB::table(self::TABLE)
            ->where('user_id', $user->getKey())
            ->where('code_hash', $row->code_hash)
            ->delete() === 1;
    }

    private function forget(StoreUser $user): void
    {
        DB::table(self::TABLE)->where('user_id', $user->getKey())->delete();
    }

    private function hash(StoreUser $user, string $code): string
    {
        return hash_hmac('sha256', $user->getKey().'|'.$code, (string) config('app.key'));
    }
}
