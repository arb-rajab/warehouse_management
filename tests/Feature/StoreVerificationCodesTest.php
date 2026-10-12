<?php

use App\Services\StoreVerificationCodes;
use Illuminate\Support\Facades\DB;

/**
 * The store's six-digit codes: stored as a hash, short-lived, usable once and
 * capped in tries. The routes that accept them are covered in
 * LegacyStoreAccountRecoveryTest and LegacyStoreApiCodesTest; these pin the
 * rules themselves, which a route's own throttle would otherwise mask.
 */
function storeCodes(): StoreVerificationCodes
{
    return app(StoreVerificationCodes::class);
}

test('a code is six digits and only its hash is stored', function () {
    $user = createStoreUser();

    $code = storeCodes()->issue($user);

    expect($code)->toMatch('/^\d{6}$/');

    $row = DB::table('wms_store_verification_codes')->where('user_id', $user->id)->first();

    expect($row->code_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($row->code_hash)->not->toContain($code)
        ->and($user->fresh()->verification_code)->toBeNull();
});

test('the right code is accepted once', function () {
    $user = createStoreUser();
    $code = storeCodes()->issue($user);

    expect(storeCodes()->consume($user, $code))->toBeTrue()
        ->and(storeCodes()->consume($user, $code))->toBeFalse()
        ->and(DB::table('wms_store_verification_codes')->count())->toBe(0);
});

test('a wrong code is refused and leaves the right one usable', function () {
    $user = createStoreUser();
    $code = storeCodes()->issue($user);

    expect(storeCodes()->consume($user, wrongCode($code)))->toBeFalse()
        ->and(storeCodes()->consume($user, $code))->toBeTrue();
});

test('one user\'s code does not work for another user, and the attempt costs the owner nothing', function () {
    $owner = createStoreUser();
    $bystander = createStoreUser();
    $ownersCode = storeCodes()->issue($owner);
    $bystandersCode = storeCodes()->issue($bystander);

    expect(storeCodes()->consume($bystander, $ownersCode))->toBeFalse();

    expect(DB::table('wms_store_verification_codes')->where('user_id', $owner->id)->value('attempts'))->toBe(0)
        ->and(storeCodes()->consume($owner, $ownersCode))->toBeTrue()
        ->and(storeCodes()->consume($bystander, $bystandersCode))->toBeTrue();
});

test('a user with no code has nothing to consume', function () {
    $user = createStoreUser();
    storeCodes()->issue(createStoreUser());

    expect(storeCodes()->consume($user, '123456'))->toBeFalse();
});

test('a code stops working when its lifetime is over', function () {
    $user = createStoreUser();
    $code = storeCodes()->issue($user);

    $this->travel(StoreVerificationCodes::LIFETIME_MINUTES - 1)->minutes();
    expect(DB::table('wms_store_verification_codes')->where('user_id', $user->id)->exists())->toBeTrue();

    $this->travel(2)->minutes();
    expect(storeCodes()->consume($user, $code))->toBeFalse()
        ->and(DB::table('wms_store_verification_codes')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('a code is dead after its allowed number of wrong tries, even for the right guess', function () {
    $user = createStoreUser();
    $code = storeCodes()->issue($user);

    foreach (range(1, StoreVerificationCodes::MAX_ATTEMPTS) as $try) {
        expect(storeCodes()->consume($user, wrongCode($code)))->toBeFalse();
    }

    expect(storeCodes()->consume($user, $code))->toBeFalse()
        ->and(DB::table('wms_store_verification_codes')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('the last allowed try can still be the right one', function () {
    $user = createStoreUser();
    $code = storeCodes()->issue($user);

    foreach (range(1, StoreVerificationCodes::MAX_ATTEMPTS - 1) as $try) {
        storeCodes()->consume($user, wrongCode($code));
    }

    expect(storeCodes()->consume($user, $code))->toBeTrue();
});

test('issuing a code replaces the previous one and resets its tries', function () {
    $user = createStoreUser();
    $first = storeCodes()->issue($user);
    storeCodes()->consume($user, wrongCode($first));

    $second = storeCodes()->issue($user);

    expect(DB::table('wms_store_verification_codes')->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('wms_store_verification_codes')->where('user_id', $user->id)->value('attempts'))->toBe(0)
        ->and(storeCodes()->consume($user, $second))->toBeTrue();
});

test('a new code is refused inside the resend cooldown, which leaves the first code valid', function () {
    $user = createStoreUser();
    $other = createStoreUser();
    $first = storeCodes()->issueUnlessCoolingDown($user);

    $this->travel(StoreVerificationCodes::RESEND_COOLDOWN_SECONDS - 5)->seconds();

    expect($first)->not->toBeNull()
        ->and(storeCodes()->issueUnlessCoolingDown($user))->toBeNull()
        // The cooldown is per user: someone else's first code is not held back.
        ->and(storeCodes()->issueUnlessCoolingDown($other))->not->toBeNull()
        ->and(storeCodes()->consume($user, $first))->toBeTrue();
});

test('a new code is issued once the cooldown has passed', function () {
    $user = createStoreUser();
    storeCodes()->issueUnlessCoolingDown($user);

    $this->travel(StoreVerificationCodes::RESEND_COOLDOWN_SECONDS + 1)->seconds();

    expect(storeCodes()->issueUnlessCoolingDown($user))->toMatch('/^\d{6}$/');
});
