<?php

namespace App\Policies;

use App\Models\CellVerificationRound;
use App\Models\User;

/**
 * A user may only view/resume/complete/report against their own verification
 * rounds — the single ownership check shared by every action that touches one.
 */
class CellVerificationRoundPolicy
{
    public function view(User $user, CellVerificationRound $cellVerificationRound): bool
    {
        return $cellVerificationRound->user_id === $user->id;
    }

    public function update(User $user, CellVerificationRound $cellVerificationRound): bool
    {
        return $this->view($user, $cellVerificationRound);
    }
}
